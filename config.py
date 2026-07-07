import os

from dotenv import load_dotenv

load_dotenv()

GRIDX_API_HOST = os.getenv("GRIDX_API_HOST", "http://localhost:8000")
GRIDX_API_KEY = os.getenv("GRIDX_API_KEY", "")

OPENROUTER_API_KEY = os.getenv("OPENROUTER_API_KEY", "")
OPENROUTER_BASE_URL = os.getenv("OPENROUTER_BASE_URL", "https://openrouter.ai/api/v1")
# Any OpenRouter model that supports tool calling. Free options:
#   nvidia/nemotron-3-ultra-550b-a55b:free   (1M ctx, agent orchestration — default)
#   nvidia/nemotron-3-super-120b-a12b:free   (1M ctx, faster)
#   poolside/laguna-m.1:free                 (262K ctx, coding-focused)
#   cohere/north-mini-code:free              (256K ctx)
AGENT_MODEL = os.getenv("AGENT_MODEL", "nvidia/nemotron-3-ultra-550b-a55b:free")

# Twilio REST credentials — used to send WhatsApp replies asynchronously
# (webhook responses must return within Twilio's ~15s timeout, but the agent
# can take longer, so replies are sent via the API instead of TwiML).
TWILIO_ACCOUNT_SID = os.getenv("TWILIO_ACCOUNT_SID", "")
TWILIO_AUTH_TOKEN = os.getenv("TWILIO_AUTH_TOKEN", "")
TWILIO_WHATSAPP_FROM = os.getenv("TWILIO_WHATSAPP_FROM", "whatsapp:+14155238886")

# When true, /webhook/* endpoints only log and reply — no order is created
# until a dispatcher approves via /approve/{draft_id}.
HUMAN_IN_THE_LOOP = os.getenv("HUMAN_IN_THE_LOOP", "true").lower() == "true"

# Routing engine for real road distances (public OSRM demo server; swap for
# a self-hosted OSRM when volume grows).
OSRM_HOST = os.getenv("OSRM_HOST", "https://router.project-osrm.org")

# Spot-quote rate card (SAR). Tune per lane/market as the business learns.
RATE_CARD = {
    "base_fee": 250.0,           # callout/handling per job
    "min_charge": 550.0,         # floor price per job
    "per_km": {                  # SAR per km by truck type
        "flatbed": 3.5,
        "curtainside": 3.8,
        "box": 3.2,
        "reefer": 4.6,
        "tanker": 5.0,
        "lowbed": 6.0,
        "default": 3.5,
    },
    "cross_border_surcharge": 800.0,  # KSA <-> UAE customs/permits
    "vat_rate": 0.15,                 # Saudi VAT
}
