import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { inject as service } from '@ember/service';
import { isArray } from '@ember/array';
import { task } from 'ember-concurrency';

/**
 * Dashboard widget: booking quotes awaiting dispatcher action. This is the
 * real entry point into the quote -> approval -> sales order -> payment ->
 * dispatch pipeline — replaces the old "AI Dispatch Approvals" widget, which
 * assumed orders (with a driver already assigned) were created directly.
 * That path no longer exists: the WhatsApp/email agent only ever creates a
 * priced quote now, never a live order.
 */
export default class GridxPendingQuotesComponent extends Component {
    @service fetch;
    @service notifications;
    @tracked quotes = [];
    @tracked busyId = null;

    constructor() {
        super(...arguments);
        this.loadPending.perform();
    }

    @task *loadPending() {
        try {
            const result = yield this.fetch.get('quotes');
            const quotes = isArray(result) ? result : (result?.quotes ?? []);
            this.quotes = quotes.filter((q) => q.status !== 'dispatched' && q.status !== 'rejected');
        } catch (err) {
            this.notifications.serverError(err);
        }
    }

    @action refresh() {
        this.loadPending.perform();
    }

    async runAction(quote, label, path) {
        this.busyId = quote.public_id;
        try {
            await this.fetch.post(`quotes/${quote.public_id}/${path}`);
            this.notifications.success(`Quote ${quote.public_id} ${label}.`);
            this.loadPending.perform();
        } catch (err) {
            this.notifications.serverError(err);
        } finally {
            this.busyId = null;
        }
    }

    @action approve(quote) {
        this.runAction(quote, 'approved', 'approve');
    }

    @action markPaid(quote) {
        this.runAction(quote, 'marked paid', 'mark-paid');
    }

    @action dispatch(quote) {
        this.runAction(quote, 'dispatched', 'dispatch');
    }
}
