import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { task } from 'ember-concurrency';

const STATUS_COLORS = {
    open: '#2563EB',
    in_progress: '#EA580C',
    on_hold: '#CA8A04',
    completed: '#16A34A',
    canceled: '#98A2B3',
};

/**
 * Modern card-grid + chart "Fleet Overview" strip at the top of Home,
 * sourced from one snapshot endpoint (FleetOverviewController) instead of
 * several separate widget calls. All real counts — no placeholder data.
 */
export default class GridxFleetOverviewComponent extends Component {
    @service fetch;
    @tracked data = null;

    constructor() {
        super(...arguments);
        this.load.perform();
    }

    @task *load() {
        this.data = yield this.fetch.get('fleet-overview');
    }

    get cards() {
        const d = this.data;
        if (!d) return [];
        return [
            { label: 'Total Trucks', value: d.total_trucks ?? 0, icon: 'truck', color: '#2563EB' },
            { label: 'Active Drivers', value: d.active_drivers ?? 0, icon: 'user', color: '#16A34A' },
            { label: 'Pending Quotes', value: d.pending_quotes ?? 0, icon: 'file-invoice-dollar', color: '#EA580C' },
            { label: 'Active Orders', value: d.active_orders ?? 0, icon: 'route', color: '#7C3AED' },
            { label: 'Open Work Orders', value: d.open_work_orders ?? 0, icon: 'wrench', color: '#DC2626' },
        ];
    }

    get maxActivity() {
        const points = this.data?.fleet_activity_7d ?? [];
        return Math.max(1, ...points.map((p) => p.orders));
    }

    // SVG polyline points for a lightweight line chart — no charting
    // dependency needed for a single 7-point series.
    get activityPolyline() {
        const points = this.data?.fleet_activity_7d ?? [];
        if (!points.length) return '';
        const w = 100 / Math.max(1, points.length - 1);
        return points.map((p, i) => `${i * w},${40 - (p.orders / this.maxActivity) * 36}`).join(' ');
    }

    get activityPoints() {
        const points = this.data?.fleet_activity_7d ?? [];
        const w = 100 / Math.max(1, points.length - 1);
        return points.map((p, i) => ({
            x: i * w,
            y: 40 - (p.orders / this.maxActivity) * 36,
            label: p.date?.slice(5),
            orders: p.orders,
        }));
    }

    get workOrderSlices() {
        const byStatus = this.data?.work_orders_by_status ?? {};
        const entries = Object.entries(byStatus);
        const total = entries.reduce((sum, [, count]) => sum + count, 0);
        if (!total) return [];

        let cumulative = 0;
        return entries.map(([status, count]) => {
            const fraction = count / total;
            const dashArray = `${fraction * 100} ${100 - fraction * 100}`;
            const dashOffset = -cumulative * 100;
            cumulative += fraction;
            return {
                status,
                count,
                percent: Math.round(fraction * 100),
                color: STATUS_COLORS[status] ?? '#98A2B3',
                dashArray,
                dashOffset,
            };
        });
    }

    get workOrderTotal() {
        const byStatus = this.data?.work_orders_by_status ?? {};
        return Object.values(byStatus).reduce((sum, c) => sum + c, 0);
    }

    get alerts() {
        return this.data?.alerts ?? [];
    }

    get topDrivers() {
        const drivers = this.data?.top_drivers ?? [];
        return drivers.map((d, i) => ({ ...d, rank: i + 1 }));
    }

    get recentActivity() {
        return this.data?.recent_activity ?? [];
    }
}
