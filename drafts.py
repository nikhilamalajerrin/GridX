"""In-memory store for order drafts awaiting dispatcher approval.

Fine for a single-process deployment; swap for a table when scaling out.
"""

import uuid
from datetime import datetime, timezone

_drafts: dict[str, dict] = {}


def add(payload: dict, sender: str, summary: str) -> str:
    draft_id = uuid.uuid4().hex[:8]
    _drafts[draft_id] = {
        "id": draft_id,
        "payload": payload,
        "sender": sender,
        "summary": summary,
        "status": "pending",
        "created_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
    }
    return draft_id


def get(draft_id: str) -> dict | None:
    return _drafts.get(draft_id)


def pending() -> list[dict]:
    return [d for d in _drafts.values() if d["status"] == "pending"]


def resolve(draft_id: str, status: str) -> dict | None:
    draft = _drafts.get(draft_id)
    if draft and draft["status"] == "pending":
        draft["status"] = status
        return draft
    return None
