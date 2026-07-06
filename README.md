# GridX Auto-Dispatch Agent

Parses inbound WhatsApp/email freight requests with Claude, picks the nearest
online driver, and creates the assigned order in GridX automatically.

```
"فلات بد من الرياض الى الدمام، ٢٤ طن اسمنت، الخميس"
        │
        ▼  Claude (tool use)
  extract job → match places/customer → rank online drivers by
  distance to pickup → create order with driver assigned
        │
        ▼
  Order appears in Fleet-Ops, live on the dispatcher's map
```

## Setup

```bash
cd gridx-agent
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env   # fill in GRIDX_API_KEY and ANTHROPIC_API_KEY
uvicorn main:app --port 9000
```

## Test without WhatsApp

```bash
curl -X POST localhost:9000/simulate -H 'content-type: application/json' -d '{
  "message": "Need a flatbed from Riyadh Central Warehouse to Dammam Logistics City, 24 tons cement, tomorrow morning",
  "sender": "+966501112222"
}'
```

The reply includes the agent's customer-facing confirmation and a `status`
(`created` / `needs_clarification` / `not_a_request`).

## Connecting real channels

- **WhatsApp**: point a Twilio WhatsApp sender or Meta Cloud API webhook at
  `POST /webhook/whatsapp`, then wire the outbound reply (`result["reply"]`)
  to the provider's send API.
- **Email**: point an inbound-parse hook (SendGrid/Mailgun/SES) at
  `POST /webhook/email`.

## Files

| File | Purpose |
|---|---|
| `agent.py` | Claude tool-use loop + dispatch tools |
| `gridx_api.py` | GridX REST client (drivers, places, contacts, orders) |
| `main.py` | FastAPI webhooks (`/simulate`, `/webhook/whatsapp`, `/webhook/email`) |
| `config.py` | Env config |
