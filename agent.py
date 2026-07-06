"""Auto-dispatch agent: parses an inbound WhatsApp/email message, matches
places and customers, picks the nearest online driver, and creates the
assigned order in GridX. Runs on any OpenRouter model with tool calling."""

import json
import logging

from openai import OpenAI

import config
from gridx_api import GridXAPI, haversine_km

log = logging.getLogger("gridx-agent")

_client: OpenAI | None = None


def get_client() -> OpenAI:
    global _client
    if _client is None:
        if not config.OPENROUTER_API_KEY:
            raise RuntimeError("OPENROUTER_API_KEY is not set in .env")
        _client = OpenAI(
            base_url=config.OPENROUTER_BASE_URL,
            api_key=config.OPENROUTER_API_KEY,
        )
    return _client


api = GridXAPI()

SYSTEM_PROMPT = """\
You are the GridX auto-dispatch agent for a trucking company operating in
Saudi Arabia and the UAE. You receive raw inbound messages (WhatsApp or
email) from customers requesting freight transport.

Your job, using the tools available:
1. Extract the job from the message: pickup location, dropoff location,
   cargo description, truck type if stated, and requested date/time.
   Messages may be in English, Arabic, or a mix.
2. Match pickup/dropoff to known GridX places when they clearly refer to
   the same location; otherwise pass the address as free text.
3. Match the sender to a known customer contact when possible.
4. Pick the best driver: online, nearest to the pickup point.
5. Create the order with the driver assigned, putting the cargo
   description and any special instructions in the notes.

Rules:
- If the message is not a transport request (greeting, invoice question,
  spam), do NOT create an order — reply with a short helpful message and
  say what you can help with.
- If pickup or dropoff is genuinely ambiguous or missing, do NOT guess —
  create nothing and draft a short clarifying question to send back.
- Never invent places, drivers, or customers; only use what the tools
  return.
- After acting, reply with a short confirmation for the customer in the
  language they wrote in (Arabic in → Arabic out), including the tracking
  number if an order was created.

End your final message with a line: STATUS: created | needs_clarification | not_a_request
"""

TOOLS = [
    {
        "type": "function",
        "function": {
            "name": "list_places",
            "description": "List the company's known places (warehouses, ports, sites) with their public_id, name, city, and coordinates.",
            "parameters": {"type": "object", "properties": {}, "required": []},
        },
    },
    {
        "type": "function",
        "function": {
            "name": "list_customers",
            "description": "List known customer contacts with their public_id, name, email, phone.",
            "parameters": {"type": "object", "properties": {}, "required": []},
        },
    },
    {
        "type": "function",
        "function": {
            "name": "find_best_driver",
            "description": "Find online drivers ranked by distance to the pickup coordinates. Returns each driver's public_id, name, vehicle, and distance in km.",
            "parameters": {
                "type": "object",
                "properties": {
                    "pickup_lat": {"type": "number", "description": "Latitude of the pickup location"},
                    "pickup_lng": {"type": "number", "description": "Longitude of the pickup location"},
                },
                "required": ["pickup_lat", "pickup_lng"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "create_order",
            "description": "Create a transport order in GridX with a driver assigned.",
            "parameters": {
                "type": "object",
                "properties": {
                    "pickup": {"type": "string", "description": "Place public_id (place_xxx) or free-form pickup address"},
                    "dropoff": {"type": "string", "description": "Place public_id (place_xxx) or free-form dropoff address"},
                    "driver": {"type": "string", "description": "Driver public_id (driver_xxx) to assign"},
                    "customer": {"type": "string", "description": "Optional customer contact public_id (contact_xxx)"},
                    "notes": {"type": "string", "description": "Cargo description and special instructions"},
                    "scheduled_at": {"type": "string", "description": "Optional ISO 8601 datetime for scheduled pickup"},
                },
                "required": ["pickup", "dropoff", "driver"],
            },
        },
    },
]


def _tool_list_places() -> str:
    places = api.list_places()
    slim = [
        {
            "id": p.get("id") or p.get("public_id"),
            "name": p.get("name"),
            "city": p.get("city"),
            "location": p.get("location"),
        }
        for p in places
    ]
    return json.dumps(slim, ensure_ascii=False)


def _tool_list_customers() -> str:
    contacts = api.list_contacts()
    slim = [
        {
            "id": c.get("id") or c.get("public_id"),
            "name": c.get("name"),
            "email": c.get("email"),
            "phone": c.get("phone"),
        }
        for c in contacts
    ]
    return json.dumps(slim, ensure_ascii=False)


def _tool_find_best_driver(pickup_lat: float, pickup_lng: float) -> str:
    drivers = api.list_drivers(online_only=True)
    ranked = []
    for d in drivers:
        loc = d.get("location") or {}
        coords = loc.get("coordinates") if isinstance(loc, dict) else None
        if not coords or len(coords) < 2:
            continue
        # GeoJSON order: [lng, lat]
        dist = haversine_km(pickup_lat, pickup_lng, coords[1], coords[0])
        ranked.append(
            {
                "id": d.get("id") or d.get("public_id"),
                "name": d.get("name"),
                "vehicle": (d.get("vehicle") or {}).get("display_name")
                if isinstance(d.get("vehicle"), dict)
                else d.get("vehicle_name"),
                "distance_km": round(dist, 1),
            }
        )
    ranked.sort(key=lambda x: x["distance_km"])
    return json.dumps(ranked[:5], ensure_ascii=False)


def _tool_create_order(
    pickup: str,
    dropoff: str,
    driver: str,
    customer: str = "",
    notes: str = "",
    scheduled_at: str = "",
) -> str:
    order = api.create_order(
        pickup=pickup,
        dropoff=dropoff,
        driver=driver,
        customer=customer or None,
        notes=notes or None,
        scheduled_at=scheduled_at or None,
    )
    tracking = order.get("tracking_number")
    slim = {
        "id": order.get("id") or order.get("public_id"),
        "tracking_number": tracking.get("tracking_number")
        if isinstance(tracking, dict)
        else order.get("tracking"),
        "status": order.get("status"),
        "driver": driver,
    }
    return json.dumps(slim, ensure_ascii=False)


TOOL_IMPLS = {
    "list_places": _tool_list_places,
    "list_customers": _tool_list_customers,
    "find_best_driver": _tool_find_best_driver,
    "create_order": _tool_create_order,
}

MAX_TURNS = 12


def dispatch_message(message: str, channel: str, sender: str) -> dict:
    """Run the agent on one inbound message. Returns the reply text and status."""
    messages = [
        {"role": "system", "content": SYSTEM_PROMPT},
        {
            "role": "user",
            "content": f"Channel: {channel}\nSender: {sender}\nMessage:\n{message}",
        },
    ]

    final_text = ""
    for _ in range(MAX_TURNS):
        response = get_client().chat.completions.create(
            model=config.AGENT_MODEL,
            messages=messages,
            tools=TOOLS,
        )
        choice = response.choices[0]
        msg = choice.message
        messages.append(msg.model_dump(exclude_none=True))

        if not msg.tool_calls:
            final_text = msg.content or ""
            break

        for call in msg.tool_calls:
            name = call.function.name
            try:
                args = json.loads(call.function.arguments or "{}")
            except json.JSONDecodeError:
                args = {}
            impl = TOOL_IMPLS.get(name)
            log.info("tool call: %s(%s)", name, args)
            try:
                result = impl(**args) if impl else f"Unknown tool: {name}"
            except Exception as exc:  # surface API errors to the model
                result = json.dumps({"error": str(exc)})
            messages.append(
                {
                    "role": "tool",
                    "tool_call_id": call.id,
                    "content": result,
                }
            )
    else:
        final_text = "The agent hit its step limit without finishing."

    status = "unknown"
    for line in final_text.splitlines():
        if line.strip().upper().startswith("STATUS:"):
            status = line.split(":", 1)[1].strip().lower()
    reply = "\n".join(
        line
        for line in final_text.splitlines()
        if not line.strip().upper().startswith("STATUS:")
    ).strip()

    return {"reply": reply, "status": status, "model": config.AGENT_MODEL}
