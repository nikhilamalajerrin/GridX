"""Auto-dispatch agent: parses an inbound WhatsApp/email message, matches
places and customers, picks the nearest online driver, and creates the
assigned order in GridX via tool use."""

import json

import anthropic
from anthropic import beta_tool

import config
from gridx_api import GridXAPI, haversine_km

client = anthropic.Anthropic()
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


@beta_tool
def list_places() -> str:
    """List the company's known places (warehouses, ports, sites) with their
    public_id, name, city, and coordinates."""
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


@beta_tool
def list_customers() -> str:
    """List known customer contacts with their public_id, name, email, phone."""
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


@beta_tool
def find_best_driver(pickup_lat: float, pickup_lng: float) -> str:
    """Find online drivers ranked by distance to the pickup coordinates.
    Returns each driver's public_id, name, vehicle, and distance in km.

    Args:
        pickup_lat: Latitude of the pickup location.
        pickup_lng: Longitude of the pickup location.
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


@beta_tool
def create_order(
    pickup: str,
    dropoff: str,
    driver: str,
    customer: str = "",
    notes: str = "",
    scheduled_at: str = "",
) -> str:
    """Create a transport order in GridX with a driver assigned.

    Args:
        pickup: Place public_id (place_xxx) or a free-form pickup address.
        dropoff: Place public_id (place_xxx) or a free-form dropoff address.
        driver: Driver public_id (driver_xxx) to assign.
        customer: Optional customer contact public_id (contact_xxx).
        notes: Cargo description and special instructions.
        scheduled_at: Optional ISO 8601 datetime for scheduled pickup.
    """
    order = api.create_order(
        pickup=pickup,
        dropoff=dropoff,
        driver=driver,
        customer=customer or None,
        notes=notes or None,
        scheduled_at=scheduled_at or None,
    )
    slim = {
        "id": order.get("id") or order.get("public_id"),
        "tracking_number": (order.get("tracking_number") or {}).get(
            "tracking_number"
        )
        if isinstance(order.get("tracking_number"), dict)
        else order.get("tracking"),
        "status": order.get("status"),
        "driver": driver,
    }
    return json.dumps(slim, ensure_ascii=False)


TOOLS = [list_places, list_customers, find_best_driver, create_order]


def dispatch_message(message: str, channel: str, sender: str) -> dict:
    """Run the agent on one inbound message. Returns the reply text and status."""
    runner = client.beta.messages.tool_runner(
        model=config.ANTHROPIC_MODEL,
        max_tokens=16000,
        system=SYSTEM_PROMPT,
        tools=TOOLS,
        messages=[
            {
                "role": "user",
                "content": (
                    f"Channel: {channel}\nSender: {sender}\n"
                    f"Message:\n{message}"
                ),
            }
        ],
    )

    final_text = ""
    for response in runner:
        for block in response.content:
            if block.type == "text":
                final_text = block.text

    status = "unknown"
    for line in final_text.splitlines():
        if line.strip().upper().startswith("STATUS:"):
            status = line.split(":", 1)[1].strip().lower()
    reply = "\n".join(
        line
        for line in final_text.splitlines()
        if not line.strip().upper().startswith("STATUS:")
    ).strip()

    return {"reply": reply, "status": status}
