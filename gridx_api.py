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
        drivers = self._get("drivers")
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

    def create_order(
        self,
        pickup: str,
        dropoff: str,
        driver: str | None = None,
        customer: str | None = None,
        notes: str | None = None,
        scheduled_at: str | None = None,
        meta: dict | None = None,
    ) -> dict:
        """Create a transport order. pickup/dropoff accept a place public_id
        (place_xxx) or a free-form address string; driver takes driver_xxx."""
        payload: dict = {
            "pickup": pickup,
            "dropoff": dropoff,
            "type": "transport",
            "adhoc": driver is None,
        }
        if driver:
            payload["driver"] = driver
        if customer:
            payload["customer"] = customer
        if notes:
            payload["notes"] = notes
        if scheduled_at:
            payload["scheduled_at"] = scheduled_at
        if meta:
            payload["meta"] = meta
        return self._post("orders", payload)


def haversine_km(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    r = 6371.0
    p1, p2 = math.radians(lat1), math.radians(lat2)
    dp, dl = math.radians(lat2 - lat1), math.radians(lon2 - lon1)
    a = math.sin(dp / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dl / 2) ** 2
    return 2 * r * math.asin(math.sqrt(a))
