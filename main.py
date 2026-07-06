"""GridX auto-dispatch agent — webhook service.

Endpoints:
  POST /simulate            {"message": "...", "sender": "..."}  — test without WhatsApp
  POST /webhook/whatsapp    Twilio-style form (Body, From) or JSON
  POST /webhook/email       {"from": "...", "subject": "...", "text": "..."}
"""

import logging
from xml.sax.saxutils import escape

import httpx
from fastapi import BackgroundTasks, FastAPI, Request
from fastapi.responses import Response

import config

from agent import dispatch_message

# Twilio retries webhooks that don't answer within ~15s; remember handled
# MessageSids so retries never double-process. In-memory is fine for a
# single-process dev deployment.
_seen_message_sids: set[str] = set()

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
log = logging.getLogger("gridx-agent")

app = FastAPI(title="GridX Auto-Dispatch Agent")


@app.get("/health")
def health():
    return {"ok": True}


@app.post("/simulate")
async def simulate(body: dict):
    message = body.get("message", "")
    sender = body.get("sender", "simulator")
    log.info("simulate from %s: %s", sender, message[:120])
    result = dispatch_message(message, channel="simulator", sender=sender)
    log.info("status=%s reply=%s", result["status"], result["reply"][:200])
    return result


def _send_whatsapp(to: str, body: str) -> None:
    """Send a WhatsApp message via the Twilio REST API."""
    if not (config.TWILIO_ACCOUNT_SID and config.TWILIO_AUTH_TOKEN):
        log.error("Twilio credentials not set — cannot send reply to %s", to)
        return
    r = httpx.post(
        f"https://api.twilio.com/2010-04-01/Accounts/{config.TWILIO_ACCOUNT_SID}/Messages.json",
        data={"To": to, "From": config.TWILIO_WHATSAPP_FROM, "Body": body},
        auth=(config.TWILIO_ACCOUNT_SID, config.TWILIO_AUTH_TOKEN),
        timeout=30,
    )
    if r.status_code >= 300:
        log.error("Twilio send failed %s: %s", r.status_code, r.text[:300])
    else:
        log.info("reply sent to %s", to)


def _process_and_reply(message: str, sender: str) -> None:
    try:
        result = dispatch_message(message, channel="whatsapp", sender=sender)
        reply = result["reply"]
    except Exception:
        log.exception("dispatch failed")
        reply = (
            "Sorry, something went wrong handling your request. "
            "A dispatcher will follow up shortly."
        )
    _send_whatsapp(sender, reply)


@app.post("/webhook/whatsapp")
async def whatsapp(request: Request, background: BackgroundTasks):
    ctype = request.headers.get("content-type", "")
    is_twilio = "form" in ctype
    if is_twilio:
        form = await request.form()
        message, sender = form.get("Body", ""), form.get("From", "")
        sid = form.get("MessageSid", "")
        if sid and sid in _seen_message_sids:
            log.info("duplicate delivery %s ignored", sid)
            return Response(
                content="<?xml version='1.0' encoding='UTF-8'?><Response/>",
                media_type="application/xml",
            )
        if sid:
            _seen_message_sids.add(sid)
        log.info("whatsapp from %s: %s", sender, message[:120])
        # Ack Twilio immediately; the agent replies via the REST API when done.
        background.add_task(_process_and_reply, message, sender)
        return Response(
            content="<?xml version='1.0' encoding='UTF-8'?><Response/>",
            media_type="application/xml",
        )
    # JSON path (tests/other providers): process synchronously.
    body = await request.json()
    message = body.get("message") or body.get("Body", "")
    sender = body.get("sender") or body.get("From", "")
    log.info("whatsapp from %s: %s", sender, message[:120])
    return dispatch_message(message, channel="whatsapp", sender=sender)


@app.post("/webhook/gridx")
async def gridx_events(request: Request, background: BackgroundTasks):
    """Receives GridX webhook events. On order.dispatched, notify the
    customer whose WhatsApp number is tagged in the order's meta."""
    event = await request.json()
    event_name = event.get("event", "")
    data = event.get("data", {}) or {}
    log.info("gridx event: %s for %s", event_name, data.get("id"))

    if event_name != "order.dispatched":
        return {"ok": True, "ignored": event_name}

    meta = data.get("meta") or {}
    sender = meta.get("whatsapp_sender", "")
    if not sender.startswith("whatsapp:"):
        log.info("order %s has no whatsapp sender tag — nothing to notify", data.get("id"))
        return {"ok": True}

    def _name(value, key="name"):
        return value.get(key, "") if isinstance(value, dict) else ""

    tracking = data.get("tracking_number")
    tracking = (
        tracking.get("tracking_number") if isinstance(tracking, dict) else
        (data.get("tracking") or "will follow")
    )
    payload_obj = data.get("payload") if isinstance(data.get("payload"), dict) else {}
    pickup = _name(payload_obj.get("pickup"))
    dropoff = _name(payload_obj.get("dropoff"))
    driver = _name(data.get("driver_assigned"))
    body = (
        "✅ Your shipment is confirmed and dispatched!\n\n"
        f"Tracking number: {tracking}\n"
        + (f"Pickup: {pickup}\n" if pickup else "")
        + (f"Dropoff: {dropoff}\n" if dropoff else "")
        + (f"Driver: {driver}\n" if driver else "")
        + "\nThe driver will contact you before pickup. — GridX"
    )
    background.add_task(_send_whatsapp, sender, body)
    return {"ok": True, "notified": sender}


@app.post("/webhook/email")
async def email(body: dict):
    sender = body.get("from", "")
    text = f"Subject: {body.get('subject', '')}\n\n{body.get('text', '')}"
    log.info("email from %s: %s", sender, text[:120])
    result = dispatch_message(text, channel="email", sender=sender)
    return result
