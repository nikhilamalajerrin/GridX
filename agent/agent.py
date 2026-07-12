"""Auto-quote agent: parses an inbound WhatsApp/email message, matches
places and customers, and creates a priced booking QUOTE in GridX — it never
creates a live order or assigns a driver directly. A human dispatcher reviews
the quote, sends it for customer approval, confirms payment, and only then
dispatches it (which is what actually creates the order). Runs on any
OpenRouter model with tool calling."""

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
4. Create a QUOTE with create_quote (pass truck_type if the cargo implies
   one, e.g. "reefer" for refrigerated goods, "flatbed" for machinery) —
   this only records the priced request, it does NOT assign a driver or
   create a dispatchable order. find_best_driver is informational only,
   for you to mention a likely driver/vehicle fit to the dispatcher if
   asked — it does not assign anyone.
5. Never create an order directly. Booking requests always become a quote
   first; a human dispatcher handles approval, payment confirmation, and
   dispatch afterward in the GridX console.

Rules:
- If the message is not a transport request (greeting, invoice question,
  spam), do NOT create a quote — reply with a short helpful message and
  say what you can help with.
- If pickup or dropoff is genuinely ambiguous or missing, do NOT guess —
  create nothing and draft a short clarifying question to send back.
- Never invent places, drivers, or customers; only use what the tools
  return.
- After acting, reply with a short confirmation for the customer in the
  language they wrote in (Arabic in → Arabic out). State the quoted price
  and that a dispatcher will follow up — never state a tracking number or
  imply the shipment is confirmed/dispatched, since it isn't yet.

Locations:
- For any pickup/dropoff that is not a known GridX place, use the geocode
  tool to resolve real coordinates. Never invent coordinates for specific
  addresses. If geocoding finds nothing, ask the customer to clarify.

Quoting and planning:
- When a customer just asks "how much would this cost" (a rough price
  inquiry, not a booking), use get_quote for a quick estimate — this does
  NOT create any record. Do not use create_quote for a casual price ask.
- Once the customer wants to actually book (confirms with "yes/book it/ok",
  or the message is clearly a booking request rather than a price check),
  use create_quote — this records a real booking quote for the dispatcher
  to review, send, and (after approval + payment) dispatch.
- For multi-stop requests, use optimize_route and present the best stop
  order with total distance and drive time.

End your final message with a line: STATUS: quote_created | quoted | needs_clarification | not_a_request
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
            "description": "Find online drivers ranked by fitness for the cargo, then distance to pickup. Returns each driver's public_id, name, vehicle, vehicle_type, payload_capacity_kg, distance_km, and a fitness_match flag.",
            "parameters": {
                "type": "object",
                "properties": {
                    "pickup_lat": {"type": "number", "description": "Latitude of the pickup location"},
                    "pickup_lng": {"type": "number", "description": "Longitude of the pickup location"},
                    "required_truck_type": {"type": "string", "description": "Truck type the cargo needs, e.g. flatbed, curtainside, box, reefer, tanker, lowbed. Omit if unspecified."},
                    "cargo_weight_kg": {"type": "number", "description": "Approximate cargo weight in kg, if known. Used to filter out vehicles without enough payload capacity."},
                },
                "required": ["pickup_lat", "pickup_lng"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "geocode",
            "description": "Resolve a free-form address or place name (Arabic or English) to coordinates via OpenStreetMap. Use this for any location that is not a known GridX place, instead of guessing coordinates.",
            "parameters": {
                "type": "object",
                "properties": {
                    "query": {"type": "string", "description": "Address or place name, e.g. 'Al Kharj industrial city' or 'حي الروضة الرياض'"},
                    "country": {"type": "string", "description": "ISO country code filter: sa, ae, or sa,ae (default sa)"}
                },
                "required": ["query"]
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
            "name": "create_quote",
            "description": (
                "Create a priced booking quote in GridX. This does NOT create a dispatchable order or "
                "assign a driver — it only records the request with dynamic pricing (distance + fuel "
                "surcharge). A human dispatcher reviews it, sends it to the customer, and only after the "
                "customer agrees, a sales order is confirmed, an approval PDF is generated, and payment is "
                "marked received, will the dispatcher dispatch it — creating the real order at that point."
            ),
            "parameters": {
                "type": "object",
                "properties": {
                    "pickup_lat": {"type": "number", "description": "Pickup latitude (from list_places or geocode)"},
                    "pickup_lng": {"type": "number", "description": "Pickup longitude"},
                    "dropoff_lat": {"type": "number", "description": "Dropoff latitude"},
                    "dropoff_lng": {"type": "number", "description": "Dropoff longitude"},
                    "pickup_address": {"type": "string", "description": "Human-readable pickup location name"},
                    "dropoff_address": {"type": "string", "description": "Human-readable dropoff location name"},
                    "truck_type": {"type": "string", "description": "flatbed, curtainside, box, reefer, tanker, or lowbed"},
                    "cargo_weight_kg": {"type": "number", "description": "Approximate cargo weight in kg, if known"},
                    "cross_border": {"type": "boolean", "description": "true if the route crosses the KSA/UAE border"},
                    "customer": {"type": "string", "description": "Optional customer contact public_id (contact_xxx)"},
                    "notes": {"type": "string", "description": "Cargo description and special instructions"},
                    "reasoning": {
                        "type": "string",
                        "description": (
                            "1-2 sentences explaining why this price/truck_type/route was chosen — "
                            "shown to the dispatcher reviewing this quote, e.g. 'Chose flatbed for the "
                            "steel coils; distance 412km KSA-only so no cross-border surcharge.'"
                        ),
                    },
                },
                "required": ["pickup_lat", "pickup_lng", "dropoff_lat", "dropoff_lng", "reasoning"],
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


def _tool_find_best_driver(
    pickup_lat: float,
    pickup_lng: float,
    required_truck_type: str | None = None,
    cargo_weight_kg: float | None = None,
) -> str:
    """Rank online drivers by fitness for the cargo, then proximity.

    Fitness signal available today: vehicle type match (if the customer
    named one) and payload capacity vs. stated cargo weight (if known) —
    both pulled straight from the vehicle record. NOT included: driver
    hours-of-service / fatigue, because GridX doesn't currently capture
    drive-time logs anywhere in the system — this ranks on fitness +
    proximity only, not fatigue, until that data exists.
    """
    drivers = api.list_drivers(online_only=True)
    ranked = []
    for d in drivers:
        loc = d.get("location") or {}
        coords = loc.get("coordinates") if isinstance(loc, dict) else None
        if not coords or len(coords) < 2:
            continue
        # GeoJSON order: [lng, lat]
        dist = haversine_km(pickup_lat, pickup_lng, coords[1], coords[0])

        vehicle = d.get("vehicle") if isinstance(d.get("vehicle"), dict) else {}
        vehicle_type = vehicle.get("type") or vehicle.get("class")
        payload_capacity = vehicle.get("payload_capacity")
        vehicle_label = vehicle.get("display_name") or vehicle.get("name") or " ".join(
            filter(None, [vehicle.get("year"), vehicle.get("make"), vehicle.get("model")])
        ) or None

        fits_type = required_truck_type is None or (
            vehicle_type and required_truck_type.lower() in str(vehicle_type).lower()
        )
        fits_capacity = cargo_weight_kg is None or (
            payload_capacity is not None and float(payload_capacity) >= cargo_weight_kg
        )
        # Vehicles with unknown type/capacity aren't excluded (missing data
        # shouldn't block dispatch) but they rank behind confirmed fits.
        fitness_match = bool(fits_type and fits_capacity)

        ranked.append(
            {
                "id": d.get("id") or d.get("public_id"),
                "name": d.get("name"),
                "vehicle": vehicle_label or d.get("vehicle_name"),
                "vehicle_type": vehicle_type,
                "payload_capacity_kg": payload_capacity,
                "distance_km": round(dist, 1),
                "fitness_match": fitness_match,
            }
        )

    # Confirmed fitness match first, then nearest within each group.
    ranked.sort(key=lambda x: (not x["fitness_match"], x["distance_km"]))
    return json.dumps(ranked[:5], ensure_ascii=False)


_current_sender = "unknown"  # set per-request by dispatch_message
_current_session_id: str | None = None  # set per-request by dispatch_message


def _tool_create_quote(
    pickup_lat: float,
    pickup_lng: float,
    dropoff_lat: float,
    dropoff_lng: float,
    pickup_address: str = "",
    dropoff_address: str = "",
    truck_type: str = "",
    cargo_weight_kg: float = 0,
    cross_border: bool = False,
    customer: str = "",
    notes: str = "",
    reasoning: str = "",
) -> str:
    quote = api.create_quote(
        pickup_lat=pickup_lat,
        pickup_lng=pickup_lng,
        dropoff_lat=dropoff_lat,
        dropoff_lng=dropoff_lng,
        pickup_address=pickup_address or None,
        dropoff_address=dropoff_address or None,
        truck_type=truck_type or None,
        cargo_weight_kg=cargo_weight_kg or None,
        cross_border=cross_border,
        customer_contact_uuid=customer or None,
        notes=notes or None,
        agent_session_id=_current_session_id,
        reasoning=reasoning or None,
    )
    slim = {
        "quote_id": quote.get("public_id"),
        "distance_km": quote.get("distance_km"),
        "total": quote.get("total"),
        "currency": quote.get("currency"),
        "status": "quote_pending",
        "note": (
            "This is a QUOTE only — no order exists and no driver is assigned yet. "
            "A dispatcher will review it in GridX, send it to the customer for approval, "
            "and only after approval + payment confirmation will it be dispatched. "
            "Tell the customer their request was received with the quoted price, and that "
            "a dispatcher will follow up to confirm. Do not state a tracking number."
        ),
    }
    return json.dumps(slim, ensure_ascii=False)


def _osrm(service: str, coords: list[tuple[float, float]], params: str = "") -> dict:
    """Call OSRM. coords are (lat, lng) pairs; OSRM wants lng,lat."""
    path = ";".join(f"{lng},{lat}" for lat, lng in coords)
    url = f"{config.OSRM_HOST}/{service}/v1/driving/{path}?{params}"
    r = httpx.get(url, timeout=20)
    r.raise_for_status()
    return r.json()


def _tool_geocode(query: str, country: str = "sa") -> str:
    """Geocode a free-form address via Nominatim (OpenStreetMap)."""
    r = httpx.get(
        "https://nominatim.openstreetmap.org/search",
        params={"q": query, "format": "json", "limit": 3, "countrycodes": country or "sa,ae"},
        headers={"User-Agent": "GridX-Agent/1.0 (support@gridx.io)"},
        timeout=15,
    )
    r.raise_for_status()
    results = [
        {"name": x.get("display_name"), "lat": float(x["lat"]), "lng": float(x["lon"])}
        for x in r.json()
    ]
    return json.dumps(results or {"error": "no matches — ask the customer to clarify the address"}, ensure_ascii=False)


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
    "create_quote": _tool_create_quote,
    "get_quote": _tool_get_quote,
    "geocode": _tool_geocode,
    "optimize_route": _tool_optimize_route,
}

MAX_TURNS = 12

# --- Conversation memory ---------------------------------------------------
# Per-sender chat history so follow-ups work ("yes book it" after a quote).
# In-memory with TTL; move to redis/db when running multiple workers.
SESSION_TTL_SECONDS = 24 * 3600
SESSION_MAX_TURNS = 16  # user+assistant text turns kept per sender

_sessions: dict[str, dict] = {}


def _session_history(sender: str) -> list[dict]:
    s = _sessions.get(sender)
    if not s or time.time() - s["ts"] > SESSION_TTL_SECONDS:
        return []
    return s["messages"]


def _session_append(sender: str, user_text: str, assistant_text: str) -> None:
    s = _sessions.setdefault(sender, {"messages": [], "ts": time.time()})
    s["messages"].append({"role": "user", "content": user_text})
    s["messages"].append({"role": "assistant", "content": assistant_text})
    s["messages"] = s["messages"][-SESSION_MAX_TURNS:]
    s["ts"] = time.time()

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
    global _current_sender, _current_session_id
    _current_sender = sender
    _current_session_id = None
    try:
        session = api.create_agent_session(mode="suggestions", channel=channel, sender=sender)
        _current_session_id = session.get("public_id")
    except Exception as exc:  # a logging failure shouldn't block the actual dispatch
        log.warning("could not open agent session: %s", exc)

    now = datetime.now(timezone(timedelta(hours=3)))  # Asia/Riyadh
    system = SYSTEM_PROMPT
    user_content = (
        f"Current date/time (Asia/Riyadh): {now.strftime('%A %Y-%m-%d %H:%M')}\n"
        f"Channel: {channel}\nSender: {sender}\nMessage:\n{message}"
    )
    messages = [
        {"role": "system", "content": system},
        *_session_history(sender),
        {"role": "user", "content": user_content},
    ]

    final_text = ""
    try:
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
    except Exception:
        if _current_session_id:
            try:
                api.complete_agent_session(_current_session_id, status="failed", summary="Agent run failed before producing a reply.")
            except Exception as exc:
                log.warning("could not close failed agent session: %s", exc)
        raise

    status = "unknown"
    for line in final_text.splitlines():
        if line.strip().upper().startswith("STATUS:"):
            status = line.split(":", 1)[1].strip().lower()
    reply = "\n".join(
        line
        for line in final_text.splitlines()
        if not line.strip().upper().startswith("STATUS:")
    ).strip()

    _session_append(sender, user_content, final_text)

    if _current_session_id:
        try:
            api.complete_agent_session(_current_session_id, status="completed", summary=final_text)
        except Exception as exc:
            log.warning("could not close agent session: %s", exc)

    return {"reply": reply, "status": status, "model": config.AGENT_MODEL}
