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
import drafts
from agent import create_order_now, dispatch_message

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


@app.get("/drafts")
def list_drafts():
    """Minimal dispatcher dashboard: pending drafts with approve/reject."""
    rows = ""
    for d in drafts.pending():
        rows += f"""
        <tr>
          <td><code>{d["id"]}</code></td>
          <td>{escape(d["summary"])}</td>
          <td>{escape(d["sender"])}</td>
          <td>{d["created_at"]}</td>
          <td>
            <form method="post" action="/drafts/{d["id"]}/approve" style="display:inline">
              <button style="background:#1B4F8A;color:#fff;border:0;padding:6px 14px;border-radius:6px;cursor:pointer">Approve</button>
            </form>
            <form method="post" action="/drafts/{d["id"]}/reject" style="display:inline">
              <button style="background:#999;color:#fff;border:0;padding:6px 14px;border-radius:6px;cursor:pointer">Reject</button>
            </form>
          </td>
        </tr>"""
    if not rows:
        rows = "<tr><td colspan=5 style='color:#888'>No pending drafts</td></tr>"
    html = f"""<!DOCTYPE html><html><head><title>GridX — Pending Dispatches</title>
    <meta http-equiv="refresh" content="15">
    <style>body{{font-family:system-ui;margin:40px;background:#F7F9FC}}
    h1{{color:#1B4F8A}} table{{border-collapse:collapse;width:100%;background:#fff;border-radius:8px;overflow:hidden}}
    th,td{{padding:10px 14px;text-align:left;border-bottom:1px solid #eee}} th{{background:#0F2D52;color:#fff}}</style>
    </head><body><h1>GridX — Pending Dispatches</h1>
    <table><tr><th>ID</th><th>Job</th><th>Customer</th><th>Received</th><th>Action</th></tr>{rows}</table>
    </body></html>"""
    return Response(content=html, media_type="text/html")


@app.post("/drafts/{draft_id}/approve")
def approve_draft(draft_id: str, background: BackgroundTasks):
    draft = drafts.resolve(draft_id, "approved")
    if not draft:
        return Response(content="Draft not found or already handled", status_code=404)
    order = create_order_now(draft["payload"])
    tracking = order.get("tracking_number")
    tracking = (
        tracking.get("tracking_number") if isinstance(tracking, dict) else tracking
    )
    log.info("draft %s approved → order %s (%s)", draft_id, order.get("id"), tracking)
    if draft["sender"].startswith("whatsapp:"):
        background.add_task(
            _send_whatsapp,
            draft["sender"],
            f"✅ Your shipment is confirmed!\n\nTracking number: {tracking}\n"
            f"{draft['summary']}\n\nYou can expect driver contact before pickup. "
            "— GridX",
        )
    return Response(
        content=f"Approved. Order {order.get('id')} created, tracking {tracking}. "
        '<a href="/drafts">Back</a>',
        media_type="text/html",
    )


@app.post("/drafts/{draft_id}/reject")
def reject_draft(draft_id: str, background: BackgroundTasks):
    draft = drafts.resolve(draft_id, "rejected")
    if not draft:
        return Response(content="Draft not found or already handled", status_code=404)
    log.info("draft %s rejected", draft_id)
    if draft["sender"].startswith("whatsapp:"):
        background.add_task(
            _send_whatsapp,
            draft["sender"],
            "We're sorry — we can't take this shipment as requested. "
            "A dispatcher will contact you to discuss alternatives. — GridX",
        )
    return Response(
        content='Rejected. <a href="/drafts">Back</a>', media_type="text/html"
    )


@app.post("/webhook/email")
async def email(body: dict):
    sender = body.get("from", "")
    text = f"Subject: {body.get('subject', '')}\n\n{body.get('text', '')}"
    log.info("email from %s: %s", sender, text[:120])
    result = dispatch_message(text, channel="email", sender=sender)
    return result
