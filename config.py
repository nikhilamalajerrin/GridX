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

# When true, /webhook/* endpoints only log and reply — no order is created
# until a dispatcher approves via /approve/{draft_id}.
HUMAN_IN_THE_LOOP = os.getenv("HUMAN_IN_THE_LOOP", "true").lower() == "true"
