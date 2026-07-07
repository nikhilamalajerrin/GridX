"""Auto-dispatch agent: parses an inbound WhatsApp/email message, matches
places and customers, picks the nearest online driver, and creates the
assigned order in GridX. Runs on any OpenRouter model with tool calling."""

import json
import logging
import time

import httpx
from datetime import datetime, timedelta, timezone

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

Quoting and planning:
- When a customer asks for a price, use get_quote (get coordinates from
  list_places, or your own knowledge for well-known GCC cities) and present
  a clean quote: distance, truck type, subtotal, 15% VAT, total in SAR,
  valid 48 hours. Do NOT create an order for a price inquiry.
- If they confirm a quoted job (yes/book it/ok), then create the order.
- For multi-stop requests, use optimize_route and present the best stop
  order with total distance and drive time.

End your final message with a line: STATUS: created | quoted | needs_clarification | not_a_request
"""

HITL_NOTE = """
NOTE: Dispatcher approval mode is ON. The create_order tool only submits a
draft for human review — no order exists until a dispatcher approves it.
When the tool returns pending_dispatcher_approval, confirm receipt to the
customer, summarize the job, and say a dispatcher will confirm shortly with
the tracking details. Do not state or invent a tracking number. Use
STATUS: created for successfully submitted drafts as well.
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
            "name": "get_quote",
            "description": "Calculate a spot freight quote in SAR using real road distance. Use place coordinates from list_places, or geocode well-known cities from your knowledge.",
            "parameters": {
                "type": "object",
                "properties": {
                    "pickup_lat": {"type": "number"},
                    "pickup_lng": {"type": "number"},
                    "dropoff_lat": {"type": "number"},
                    "dropoff_lng": {"type": "number"},
                    "truck_type": {"type": "string", "description": "flatbed, curtainside, box, reefer, tanker, or lowbed"},
                    "cross_border": {"type": "boolean", "description": "true if the route crosses KSA/UAE border"}
                },
                "required": ["pickup_lat", "pickup_lng", "dropoff_lat", "dropoff_lng"]
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "optimize_route",
            "description": "Find the optimal visiting order for a multi-stop route (3+ stops, round trip from the first stop). Returns best order, total km and drive time.",
            "parameters": {
                "type": "object",
                "properties": {
                    "stops_json": {"type": "string", "description": "JSON array of stops: [{\"name\": ..., \"lat\": ..., \"lng\": ...}, ...] — first stop is the depot/start"}
                },
                "required": ["stops_json"]
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


_current_sender = "unknown"  # set per-request by dispatch_message


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
        # Tag the order with the requester so the dispatch webhook can
        # notify them on WhatsApp when a dispatcher confirms.
        meta={"whatsapp_sender": _current_sender, "source": "gridx-agent"},
    )
    if config.HUMAN_IN_THE_LOOP:
        slim = {
            "id": order.get("id") or order.get("public_id"),
            "status": "pending_dispatcher_confirmation",
            "note": (
                "Order recorded but NOT yet confirmed — a dispatcher must "
                "review and dispatch it in Fleet-Ops. Tell the customer the "
                "request is received and a dispatcher will confirm shortly "
                "with tracking details. Do not state a tracking number."
            ),
        }
        return json.dumps(slim, ensure_ascii=False)
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


def _osrm(service: str, coords: list[tuple[float, float]], params: str = "") -> dict:
    """Call OSRM. coords are (lat, lng) pairs; OSRM wants lng,lat."""
    path = ";".join(f"{lng},{lat}" for lat, lng in coords)
    url = f"{config.OSRM_HOST}/{service}/v1/driving/{path}?{params}"
    r = httpx.get(url, timeout=20)
    r.raise_for_status()
    return r.json()


def _tool_get_quote(
    pickup_lat: float,
    pickup_lng: float,
    dropoff_lat: float,
    dropoff_lng: float,
    truck_type: str = "flatbed",
    cross_border: bool = False,
) -> str:
    rc = config.RATE_CARD
    route = _osrm("route", [(pickup_lat, pickup_lng), (dropoff_lat, dropoff_lng)])
    leg = route["routes"][0]
    distance_km = round(leg["distance"] / 1000, 1)
    duration_h = round(leg["duration"] / 3600, 1)
    rate = rc["per_km"].get(truck_type.lower(), rc["per_km"]["default"])
    subtotal = max(rc["base_fee"] + distance_km * rate, rc["min_charge"])
    if cross_border:
        subtotal += rc["cross_border_surcharge"]
    vat = round(subtotal * rc["vat_rate"], 2)
    return json.dumps(
        {
            "distance_km": distance_km,
            "drive_time_hours": duration_h,
            "truck_type": truck_type,
            "rate_per_km_sar": rate,
            "subtotal_sar": round(subtotal, 2),
            "vat_15pct_sar": vat,
            "total_sar": round(subtotal + vat, 2),
            "valid_for": "48 hours",
        },
        ensure_ascii=False,
    )


def _tool_optimize_route(stops_json: str) -> str:
    """stops_json: JSON array of {name, lat, lng}. Returns optimal visiting
    order (round trip from the first stop) with total distance/time."""
    stops = json.loads(stops_json)
    if len(stops) < 3:
        return json.dumps({"error": "Need at least 3 stops to optimize."})
    coords = [(s["lat"], s["lng"]) for s in stops]
    trip = _osrm("trip", coords, "source=first&roundtrip=true")
    t = trip["trips"][0]
    order = sorted(range(len(stops)), key=lambda i: trip["waypoints"][i]["waypoint_index"])
    return json.dumps(
        {
            "optimal_order": [stops[i]["name"] for i in order],
            "total_distance_km": round(t["distance"] / 1000, 1),
            "total_drive_time_hours": round(t["duration"] / 3600, 1),
        },
        ensure_ascii=False,
    )


TOOL_IMPLS = {
    "list_places": _tool_list_places,
    "list_customers": _tool_list_customers,
    "find_best_driver": _tool_find_best_driver,
    "create_order": _tool_create_order,
    "get_quote": _tool_get_quote,
    "optimize_route": _tool_optimize_route,
}

MAX_TURNS = 12

# Free-tier providers hit capacity limits; try these in order.
MODEL_CHAIN = [
    config.AGENT_MODEL,
    "nvidia/nemotron-3-super-120b-a12b:free",
    "poolside/laguna-m.1:free",
]


def _chat_with_fallback(messages: list, tools: list):
    """Call OpenRouter, retrying transient failures and walking the model
    chain when a free-tier provider is exhausted."""
    last_err: Exception | None = None
    for model in MODEL_CHAIN:
        for attempt in range(3):
            response = get_client().chat.completions.create(
                model=model, messages=messages, tools=tools
            )
            if response.choices:
                return response
            err = getattr(response, "error", None) or response.model_extra
            last_err = RuntimeError(f"{model}: {err}")
            log.warning("attempt %d on %s failed: %s", attempt + 1, model, err)
            time.sleep(2**attempt)
        log.warning("model %s exhausted, falling back", model)
    raise last_err or RuntimeError("all models in chain failed")


def dispatch_message(message: str, channel: str, sender: str) -> dict:
    """Run the agent on one inbound message. Returns the reply text and status."""
    global _current_sender
    _current_sender = sender
    now = datetime.now(timezone(timedelta(hours=3)))  # Asia/Riyadh
    system = SYSTEM_PROMPT + (HITL_NOTE if config.HUMAN_IN_THE_LOOP else "")
    messages = [
        {"role": "system", "content": system},
        {
            "role": "user",
            "content": (
                f"Current date/time (Asia/Riyadh): {now.strftime('%A %Y-%m-%d %H:%M')}\n"
                f"Channel: {channel}\nSender: {sender}\nMessage:\n{message}"
            ),
        },
    ]

    final_text = ""
    for _ in range(MAX_TURNS):
        response = _chat_with_fallback(messages, TOOLS)
        msg = response.choices[0].message
        # Echo back only standard fields — OpenRouter models attach extras
        # like `reasoning` that break the next request if resent.
        assistant_msg: dict = {"role": "assistant", "content": msg.content or ""}
        if msg.tool_calls:
            assistant_msg["tool_calls"] = [
                {
                    "id": c.id,
                    "type": "function",
                    "function": {
                        "name": c.function.name,
                        "arguments": c.function.arguments,
                    },
                }
                for c in msg.tool_calls
            ]
        messages.append(assistant_msg)

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
