import Component from '@glimmer/component';
import { action } from '@ember/object';
import { colorForLegIndex, waypointIconHtml } from '../../utils/gridx-route-colors';

export default class GridxRoutePlanMapComponent extends Component {
    get stops() {
        return this.args.stops ?? [];
    }

    get lines() {
        return this.args.lines ?? [];
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
