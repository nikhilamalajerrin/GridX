import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { registerDestructor } from '@ember/destroyable';
import { colorForLegIndex, waypointIconHtml, truckIconHtml, haversineKm, bearingDeg } from '../../utils/gridx-route-colors';

// However long the real route is, a truck completes one full lap of its
// route in this many seconds — keeps the animation watchable regardless of
// whether the plan spans 20km or 2000km, since this is a visual "the fleet
// is moving" cue, not a real-time ETA simulation.
const LOOP_SECONDS = 22;
const FRAME_INTERVAL_MS = 66; // ~15fps — smooth enough for marker movement, far less render churn than 60fps

export default class GridxRoutePlanMapComponent extends Component {
    @tracked truckPositions = [];
    animationFrameId = null;
    animationStart = null;
    lastFrameTime = 0;

    constructor() {
        super(...arguments);
        this.animationStart = performance.now();
        this.animationFrameId = requestAnimationFrame(this.tick);
        registerDestructor(this, () => cancelAnimationFrame(this.animationFrameId));
    }

    get stops() {
        return this.args.stops ?? [];
    }

    get lines() {
        return this.args.lines ?? [];
    }

    // One continuous path per vehicle — its legs, in visiting order, joined
    // end to end — so a single truck marker travels the whole route instead
    // of resetting at every stop.
    get vehiclePaths() {
        const byVehicle = new Map();
        for (const line of this.lines) {
            if (!byVehicle.has(line.vehicleId)) byVehicle.set(line.vehicleId, []);
            byVehicle.get(line.vehicleId).push(line);
        }

        const paths = [];
        for (const [vehicleId, legs] of byVehicle.entries()) {
            const ordered = [...legs].sort((a, b) => a.legIndex - b.legIndex);
            const points = [];
            ordered.forEach((leg, i) => {
                const legPoints = i === 0 ? leg.points : leg.points.slice(1);
                points.push(...legPoints);
            });
            if (points.length < 2) continue;

            let totalKm = 0;
            const cumulative = [0];
            for (let i = 1; i < points.length; i++) {
                totalKm += haversineKm(points[i - 1], points[i]);
                cumulative.push(totalKm);
            }

            paths.push({ vehicleId, points, cumulative, totalKm, color: colorForLegIndex(ordered[0].legIndex) });
        }
        return paths;
    }

    @action
    tick(timestamp) {
        if (timestamp - this.lastFrameTime < FRAME_INTERVAL_MS) {
            this.animationFrameId = requestAnimationFrame(this.tick);
            return;
        }
        this.lastFrameTime = timestamp;

        const elapsedSeconds = (timestamp - this.animationStart) / 1000;

        this.truckPositions = this.vehiclePaths.map((path) => {
            const fraction = path.totalKm > 0 ? (elapsedSeconds % LOOP_SECONDS) / LOOP_SECONDS : 0;
            const targetKm = fraction * path.totalKm;

            let segmentIndex = path.cumulative.findIndex((km) => km >= targetKm);
            if (segmentIndex <= 0) segmentIndex = 1;

            const [prevKm, nextKm] = [path.cumulative[segmentIndex - 1], path.cumulative[segmentIndex]];
            const segFraction = nextKm > prevKm ? (targetKm - prevKm) / (nextKm - prevKm) : 0;
            const a = path.points[segmentIndex - 1];
            const b = path.points[segmentIndex];

            return {
                vehicleId: path.vehicleId,
                lat: a[0] + (b[0] - a[0]) * segFraction,
                lng: a[1] + (b[1] - a[1]) * segFraction,
                bearing: bearingDeg(a, b),
                color: path.color,
            };
        });

        this.animationFrameId = requestAnimationFrame(this.tick);
    }

    @action
    truckIconHtml(truck) {
        return truckIconHtml(truck.bearing, truck.color);
    }

    // Color by visiting order (leg 1 green, leg 2 amber, leg 3 red, ...) so
    // the map itself shows which way to go first — not just a flat color
    // per vehicle with no sense of direction.
    @action
    lineColor(line) {
        return colorForLegIndex(line.legIndex ?? 0);
    }

    get mapCenter() {
        const stops = this.stops;
        if (!stops.length) {
            return { lat: 24.7136, lng: 46.6753 }; // Riyadh, sensible default
        }

        const lat = stops.reduce((sum, s) => sum + s.lat, 0) / stops.length;
        const lng = stops.reduce((sum, s) => sum + s.lng, 0) / stops.length;
        return { lat, lng };
    }

    get mapZoom() {
        return this.stops.length ? 10 : 6;
    }

    get tileSourceUrl() {
        return 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png';
    }

    @action
    waypointIconHtml(stop) {
        // Same color family as the leg leading into this stop, so the
        // marker and the road segment before it visually match up.
        const color = colorForLegIndex(Math.max(0, (stop.stopIndex ?? 1) - 1));
        const typeSuffix = stop.type === 'pickup' ? 'P' : 'D';
        return waypointIconHtml(`${stop.stopIndex}${typeSuffix}`, color);
    }

    @action
    onMapLoad() {
        // no-op, present so @onLoad has a target
    }
}
