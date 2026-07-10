import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { tracked } from '@glimmer/tracking';

export default class RoutePlanningController extends Controller {
    @service routePlanning;
    @service notifications;

    @tracked selectedQuoteIds = [];
    @tracked selectedVehicleIds = [];
    @tracked plan = null;
    @tracked isPlanning = false;
    @tracked isCommitting = false;
    @tracked committed = false;
    @tracked geometryLines = [];
    @tracked isLoadingGeometry = false;
    @tracked tripDocs = null;
    @tracked tripDocsFor = null;
    @tracked isLoadingTripDocs = false;

    get quotes() {
        return this.model.quotes;
    }

    get vehicles() {
        return this.model.vehicles;
    }

    get canPlan() {
        return this.selectedQuoteIds.length > 0 && this.selectedVehicleIds.length > 0 && !this.isPlanning;
    }

    // The greedy fallback engine's response explicitly names itself; VROOM's
    // native response doesn't have an "engine" field, only VROOM-specific
    // fields like computing_times — infer from that instead of showing blank.
    get planEngine() {
        const summary = this.plan?.summary;
        if (!summary) return null;
        if (summary.engine) return summary.engine;
        if (summary.computing_times) return 'vroom';
        return null;
    }

    get mapStops() {
        if (!this.plan) return [];

        const stops = [];
        for (const assignment of this.plan.assignments || []) {
            const detail = this.plan.orderDetails?.[assignment.order_id];
            if (!detail) continue;

            stops.push({
                orderId: assignment.order_id,
                vehicleId: assignment.vehicle_id,
                sequence: assignment.sequence,
                type: 'pickup',
                lat: detail.pickup.lat,
                lng: detail.pickup.lng,
                name: detail.pickup.name,
            });
            stops.push({
                orderId: assignment.order_id,
                vehicleId: assignment.vehicle_id,
                sequence: assignment.sequence,
                type: 'dropoff',
                lat: detail.dropoff.lat,
                lng: detail.dropoff.lng,
                name: detail.dropoff.name,
            });
        }

        const valid = stops.filter((s) => typeof s.lat === 'number' && typeof s.lng === 'number');

        // Assign a GLOBAL visiting-order number per vehicle (1, 2, 3, 4...) —
        // this is what actually answers "which stop first, second, third",
        // not the per-order `sequence` (which two stops from the same order
        // both share). Also used as the marker label instead of a plain "P"/
        // "D" so the driver can read the route as a numbered checklist.
        const byVehicle = new Map();
        for (const stop of valid) {
            if (!byVehicle.has(stop.vehicleId)) byVehicle.set(stop.vehicleId, []);
            byVehicle.get(stop.vehicleId).push(stop);
        }
        for (const vehicleStops of byVehicle.values()) {
            vehicleStops
                .sort((a, b) => a.sequence - b.sequence || (a.type === 'pickup' ? -1 : 1))
                .forEach((stop, i) => {
                    stop.stopIndex = i + 1;
                    stop.label = `${i + 1}`;
                });
        }

        return valid;
    }

    // One polyline PER LEG (between consecutive stops), not one line for the
    // whole route — so each leg can be colored by visiting order (1st leg
    // green, 2nd amber, 3rd red, ...) instead of the whole route being a
    // single flat color with no indication of which way to go first.
    get routeLines() {
        const byVehicle = new Map();
        for (const stop of this.mapStops) {
            if (!byVehicle.has(stop.vehicleId)) byVehicle.set(stop.vehicleId, []);
            byVehicle.get(stop.vehicleId).push(stop);
        }

        const legs = [];
        for (const [vehicleId, stops] of byVehicle.entries()) {
            const ordered = [...stops].sort((a, b) => a.stopIndex - b.stopIndex);
            for (let i = 0; i < ordered.length - 1; i++) {
                legs.push({
                    vehicleId,
                    legIndex: i,
                    points: [
                        [ordered[i].lat, ordered[i].lng],
                        [ordered[i + 1].lat, ordered[i + 1].lng],
                    ],
                });
            }
        }

        return legs;
    }

    // Real road-following geometry once loaded; falls back to the straight
    // line so the map shows *something* immediately while OSRM responds.
    get mapLines() {
        return this.geometryLines.length ? this.geometryLines : this.routeLines;
    }

    async loadRouteGeometry() {
        this.isLoadingGeometry = true;
        try {
            const legs = this.routeLines;
            const withGeometry = await Promise.all(
                legs.map(async (leg) => ({
                    vehicleId: leg.vehicleId,
                    legIndex: leg.legIndex,
                    points: await this.routePlanning.fetchRouteGeometry(leg.points),
                }))
            );
            this.geometryLines = withGeometry;
        } catch (e) {
            // Straight-line fallback via mapLines getter is sufficient here.
            this.geometryLines = [];
        } finally {
            this.isLoadingGeometry = false;
        }
    }

    @action
    toggleQuote(quoteId) {
        this.selectedQuoteIds = this.selectedQuoteIds.includes(quoteId) ? this.selectedQuoteIds.filter((id) => id !== quoteId) : [...this.selectedQuoteIds, quoteId];
    }

    @action
    toggleVehicle(vehicleId) {
        this.selectedVehicleIds = this.selectedVehicleIds.includes(vehicleId) ? this.selectedVehicleIds.filter((id) => id !== vehicleId) : [...this.selectedVehicleIds, vehicleId];
    }

    @action
    async runPlan() {
        this.isPlanning = true;
        this.committed = false;
        this.geometryLines = [];
        try {
            this.plan = await this.routePlanning.planRoute(this.selectedQuoteIds, this.selectedVehicleIds);
            this.notifications.success(`Planned ${this.plan.assignments?.length ?? 0} stop(s) across ${this.selectedVehicleIds.length} vehicle(s).`);
            this.loadRouteGeometry();
        } catch (e) {
            this.notifications.error(e.message || 'Route planning failed.');
            // The selection may include a quote someone else already dispatched
            // (e.g. from a previous test run) — refresh so the list reflects
            // reality instead of leaving a stale, already-processed quote checked.
            this.model = { ...this.model, quotes: await this.routePlanning.fetchPaidQuotes() };
            const stillPaid = new Set(this.model.quotes.map((q) => q.public_id));
            this.selectedQuoteIds = this.selectedQuoteIds.filter((id) => stillPaid.has(id));
        } finally {
            this.isPlanning = false;
        }
    }

    @action
    async viewTripDocuments(assignment) {
        this.tripDocsFor = assignment.order_id;
        this.isLoadingTripDocs = true;
        try {
            this.tripDocs = await this.routePlanning.fetchTripDocuments(assignment.driver_id, assignment.vehicle_id);
        } catch (e) {
            this.notifications.error(e.message || 'Failed to load trip documents.');
            this.tripDocsFor = null;
        } finally {
            this.isLoadingTripDocs = false;
        }
    }

    @action
    closeTripDocuments() {
        this.tripDocsFor = null;
        this.tripDocs = null;
    }

    @action
    async commitPlan() {
        if (!this.plan?.assignments?.length) return;

        this.isCommitting = true;
        try {
            await this.routePlanning.commitPlan(this.plan.assignments);
            this.committed = true;
            this.notifications.success('Route plan committed — drivers and vehicles assigned.');
        } catch (e) {
            this.notifications.error(e.message || 'Failed to commit route plan.');
        } finally {
            this.isCommitting = false;
        }
    }
}
