"""Thin client for the GridX (Fleetbase-compatible) public REST API."""

import math

import httpx

import config


class GridXAPI:
    def __init__(self, host: str | None = None, key: str | None = None):
        self.host = (host or config.GRIDX_API_HOST).rstrip("/")
        self.key = key or config.GRIDX_API_KEY
        self._client = httpx.Client(
            base_url=f"{self.host}/v1",
            headers={"Authorization": f"Bearer {self.key}"},
            timeout=30,
        )

    def _get(self, path: str, **params):
        r = self._client.get(path, params=params)
        r.raise_for_status()
        return r.json()

    def _post(self, path: str, payload: dict):
        r = self._client.post(path, json=payload)
        r.raise_for_status()
        return r.json()

    # -- resources -------------------------------------------------------

    def list_drivers(self, online_only: bool = True) -> list[dict]:
        # with[]=vehicle embeds vehicle type/payload capacity so the dispatch
        # agent can rank by fitness-for-cargo, not just distance.
        drivers = self._get("drivers", **{"with[]": "vehicle"})
        if isinstance(drivers, dict):
            drivers = drivers.get("data", drivers.get("drivers", []))
        if online_only:
            drivers = [d for d in drivers if d.get("online")]
        return drivers

    def list_places(self) -> list[dict]:
        places = self._get("places")
        if isinstance(places, dict):
            places = places.get("data", places.get("places", []))
        return places

    def list_contacts(self) -> list[dict]:
        contacts = self._get("contacts")
        if isinstance(contacts, dict):
            contacts = contacts.get("data", contacts.get("contacts", []))
        return contacts

    def create_quote(
        self,
        pickup_lat: float,
        pickup_lng: float,
        dropoff_lat: float,
        dropoff_lng: float,
        pickup_address: str | None = None,
        dropoff_address: str | None = None,
        truck_type: str | None = None,
        cargo_weight_kg: float | None = None,
        cross_border: bool = False,
        customer_contact_uuid: str | None = None,
        notes: str | None = None,
        agent_session_id: str | None = None,
        reasoning: str | None = None,
    ) -> dict:
        """Create a booking quote (dynamic pricing: distance + fuel surcharge).
        This does NOT create a dispatchable order or assign a driver — a human
        dispatcher must approve, confirm payment, then dispatch it via the
        console. Use this instead of creating an order directly."""
        payload: dict = {
            "pickup_lat": pickup_lat,
            "pickup_lng": pickup_lng,
            "dropoff_lat": dropoff_lat,
            "dropoff_lng": dropoff_lng,
            "cross_border": cross_border,
        }
        if pickup_address:
            payload["pickup_address"] = pickup_address
        if dropoff_address:
            payload["dropoff_address"] = dropoff_address
        if truck_type:
            payload["truck_type"] = truck_type
        if cargo_weight_kg:
            payload["cargo_weight_kg"] = cargo_weight_kg
        if customer_contact_uuid:
            payload["customer_contact_uuid"] = customer_contact_uuid
        if notes:
            payload["notes"] = notes
        if agent_session_id:
            payload["agent_session_id"] = agent_session_id
        if reasoning:
            payload["agent_reasoning"] = reasoning
        return self._post("quotes", payload)

    def create_agent_session(self, mode: str = "suggestions", channel: str | None = None, sender: str | None = None) -> dict:
        """Open a session for one agent run, so a dispatcher can review the
        agent's reasoning and outcome even when no quote was created."""
        payload: dict = {"mode": mode}
        if channel:
            payload["channel"] = channel
        if sender:
            payload["sender"] = sender
        result = self._post("agent-sessions", payload)
        return result.get("session", result)

    def complete_agent_session(self, session_id: str, status: str, summary: str | None = None) -> dict:
        payload: dict = {"status": status}
        if summary:
            payload["summary"] = summary
        result = self._post(f"agent-sessions/{session_id}/complete", payload)
        return result.get("session", result)


def haversine_km(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    r = 6371.0
    p1, p2 = math.radians(lat1), math.radians(lat2)
    dp, dl = math.radians(lat2 - lat1), math.radians(lon2 - lon1)
    a = math.sin(dp / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dl / 2) ** 2
    return 2 * r * math.asin(math.sqrt(a))
