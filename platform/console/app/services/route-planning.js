import Service, { inject as service } from '@ember/service';
import config from 'ember-get-config';

/**
 * Multi-warehouse consolidated route planning: select several paid quotes
 * (potentially from different pickup warehouses), convert them to real
 * Orders in one batch, run the VROOM-backed Orchestrator to sequence them
 * onto the fleet's trucks, then commit the plan (creates Manifests, assigns
 * driver/vehicle to each order).
 */
export default class RoutePlanningService extends Service {
    @service session;
    @service notifications;

    get authToken() {
        const authenticated = this.session?.data?.authenticated;
        if (authenticated?.token) {
            return authenticated.token;
        }
        try {
            const stored = JSON.parse(window.localStorage.getItem('ember_simple_auth-session'));
            return stored?.authenticated?.token;
        } catch (e) {
            return undefined;
        }
    }

    async request(path, method = 'GET', body) {
        const response = await fetch(`${config.API.host}/${config.API.namespace}/${path}`, {
            method,
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
                Authorization: `Bearer ${this.authToken}`,
            },
            body: body ? JSON.stringify(body) : undefined,
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(payload.error || payload.message || `Request failed (${response.status})`);
        }

        return payload;
    }

    async fetchPaidQuotes() {
        const { quotes } = await this.request('quotes');
        return (quotes || []).filter((q) => q.status === 'paid');
    }

    async fetchVehicles() {
        const result = await this.request('vehicles');
        return result?.vehicles ?? result?.data ?? (Array.isArray(result) ? result : []);
    }

    /**
     * Converts the given quotes to unassigned Orders, then runs the
     * Orchestrator (VROOM engine) across them and the given vehicles.
     * Returns { orders, assignments, unassigned, summary } — orders carries
     * the pickup/dropoff coordinates needed to draw the map.
     */
    async planRoute(quoteIds, vehicleIds) {
        const batch = await this.request('quotes/batch-dispatch', 'POST', { quote_ids: quoteIds });
        const converted = (batch.results || []).filter((r) => r.order_id);
        if (converted.length === 0) {
            throw new Error('No quotes could be converted to orders (already dispatched or not paid).');
        }

        const orderDetails = {};
        converted.forEach((r) => {
            orderDetails[r.order_id] = { pickup: r.pickup, dropoff: r.dropoff, quote: r.quote };
        });

        const plan = await this.request('fleet-ops/orchestrator/run', 'POST', {
            mode: 'assign_vehicles',
            order_ids: converted.map((r) => r.order_id),
            vehicle_ids: vehicleIds,
        });

        return { ...plan, orderDetails };
    }

    async commitPlan(assignments) {
        return this.request('fleet-ops/orchestrator/commit', 'POST', { assignments });
    }

    /**
     * Real road-following polyline through an ordered list of [lat, lng]
     * stops, via self-hosted OSRM. Falls back to the straight-line points
     * (what the caller already has) if OSRM can't route it, rather than
     * failing the whole plan over a map-visualization nicety.
     */
    async fetchRouteGeometry(points) {
        try {
            const { geometry } = await this.request('route-geometry', 'POST', { points });
            return geometry;
        } catch (e) {
            return points;
        }
    }

    /**
     * Trip documents: what's already on file for the driver + vehicle
     * assigned to a stop — photo, plate, license number, contact info.
     * Display-only, no file upload (existing structured data, not scans).
     */
    async fetchTripDocuments(driverId, vehicleId) {
        const [driverResult, vehicleResult] = await Promise.all([driverId ? this.request(`drivers/${driverId}`) : null, vehicleId ? this.request(`vehicles/${vehicleId}`) : null]);

        return {
            driver: driverResult?.driver ?? null,
            vehicle: vehicleResult?.vehicle ?? null,
        };
    }
}
