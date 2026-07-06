"""GridX auto-dispatch agent — webhook service.

Endpoints:
  POST /simulate            {"message": "...", "sender": "..."}  — test without WhatsApp
  POST /webhook/whatsapp    Twilio-style form (Body, From) or JSON
  POST /webhook/email       {"from": "...", "subject": "...", "text": "..."}
"""

import logging

from fastapi import FastAPI, Request

from agent import dispatch_message

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


@app.post("/webhook/whatsapp")
async def whatsapp(request: Request):
    ctype = request.headers.get("content-type", "")
    if "form" in ctype:
        form = await request.form()
        message, sender = form.get("Body", ""), form.get("From", "")
    else:
        body = await request.json()
        message = body.get("message") or body.get("Body", "")
        sender = body.get("sender") or body.get("From", "")
    log.info("whatsapp from %s: %s", sender, message[:120])
    result = dispatch_message(message, channel="whatsapp", sender=sender)
    # Twilio expects TwiML; Meta Cloud API expects a 200 + separate send call.
    # Wire the actual reply transport when you connect a provider.
    return result


@app.post("/webhook/email")
async def email(body: dict):
    sender = body.get("from", "")
    text = f"Subject: {body.get('subject', '')}\n\n{body.get('text', '')}"
    log.info("email from %s: %s", sender, text[:120])
    result = dispatch_message(text, channel="email", sender=sender)
    return result
