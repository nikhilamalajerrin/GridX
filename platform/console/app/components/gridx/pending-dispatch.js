import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';
import { isArray } from '@ember/array';
import { task } from 'ember-concurrency';

/**
 * Dashboard widget: orders awaiting dispatcher approval — primarily those
 * created by the GridX AI agent from WhatsApp/email requests. One-click
 * dispatch (= approval) notifies the customer via the agent webhook.
 */
export default class GridxPendingDispatchComponent extends Component {
    @service fetch;
    @service notifications;
    @tracked orders = [];

    constructor() {
        super(...arguments);
        this.loadPending.perform();
    }

    @task *loadPending() {
        try {
            const result = yield this.fetch.get('orders', {
                status: 'created',
                sort: '-created_at',
                limit: 20,
            });
            const orders = isArray(result) ? result : result?.orders ?? result?.data ?? [];
            this.orders = orders.filter((order) => !order.dispatched && !order.dispatched_at);
        } catch (err) {
            this.notifications.serverError(err);
        }
    }

    isAgentOrder(order) {
        return order?.meta?.source === 'gridx-agent';
    }

    @action refresh() {
        this.loadPending.perform();
    }

    @action async dispatchOrder(order) {
        try {
            await this.fetch.patch('orders/dispatch', { order: order.id ?? order.public_id });
            this.orders = this.orders.filter((o) => o !== order);
            this.notifications.success(`Order ${order.tracking_number?.tracking_number ?? order.public_id} dispatched — customer will be notified.`);
        } catch (err) {
            this.notifications.serverError(err);
        }
    }
}
