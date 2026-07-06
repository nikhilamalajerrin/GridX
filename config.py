import os

from dotenv import load_dotenv

load_dotenv()

GRIDX_API_HOST = os.getenv("GRIDX_API_HOST", "http://localhost:8000")
GRIDX_API_KEY = os.getenv("GRIDX_API_KEY", "")

ANTHROPIC_MODEL = os.getenv("ANTHROPIC_MODEL", "claude-opus-4-8")

# When true, /webhook/* endpoints only log and reply — no order is created
# until a dispatcher approves via /approve/{draft_id}.
HUMAN_IN_THE_LOOP = os.getenv("HUMAN_IN_THE_LOOP", "true").lower() == "true"
