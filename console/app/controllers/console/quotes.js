import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { tracked } from '@glimmer/tracking';

export default class QuotesController extends Controller {
    @service quotes;
    @service notifications;

    @tracked busyId = null;
    @tracked editingQuote = null;
    @tracked editForm = null;
    @tracked isSavingEdit = false;

    async refresh() {
        this.model = await this.quotes.fetchAll();
    }

    async perform(id, label, method) {
        this.busyId = id;
        try {
            await this.quotes.runAction(label, id, (quoteId) => method.call(this.quotes, quoteId));
            await this.refresh();
        } finally {
            this.busyId = null;
        }
    }

    @action
    approve(id) {
        this.perform(id, 'approved', this.quotes.approve);
    }

    @action
    reject(id) {
        this.perform(id, 'rejected', this.quotes.reject);
    }

    @action
    markPaid(id) {
        this.perform(id, 'marked paid', this.quotes.markPaid);
    }

    @action
    dispatch(id) {
        this.perform(id, 'dispatched', this.quotes.dispatch);
    }

    @action
    viewPdf(id) {
        this.quotes.openPdf(id);
    }

    @action
    openEdit(quote) {
        this.editingQuote = quote;
        this.editForm = {
            pickup_address: quote.pickup_address ?? '',
            dropoff_address: quote.dropoff_address ?? '',
            pickup_lat: quote.pickup_lat,
            pickup_lng: quote.pickup_lng,
            dropoff_lat: quote.dropoff_lat,
            dropoff_lng: quote.dropoff_lng,
            truck_type: quote.truck_type ?? 'default',
            cargo_weight_kg: quote.cargo_weight_kg ?? '',
            notes: quote.notes ?? '',
        };
    }

    @action
    closeEdit() {
        this.editingQuote = null;
        this.editForm = null;
    }

    @action
    updateEditField(field, event) {
        const value = event.target.value;
        this.editForm = { ...this.editForm, [field]: value };
    }

    @action
    async saveEdit() {
        if (!this.editingQuote) return;

        this.isSavingEdit = true;
        try {
            await this.quotes.update(this.editingQuote.public_id, {
                pickup_address: this.editForm.pickup_address,
                dropoff_address: this.editForm.dropoff_address,
                pickup_lat: parseFloat(this.editForm.pickup_lat),
                pickup_lng: parseFloat(this.editForm.pickup_lng),
                dropoff_lat: parseFloat(this.editForm.dropoff_lat),
                dropoff_lng: parseFloat(this.editForm.dropoff_lng),
                truck_type: this.editForm.truck_type,
                cargo_weight_kg: this.editForm.cargo_weight_kg ? parseFloat(this.editForm.cargo_weight_kg) : null,
                notes: this.editForm.notes,
            });
            this.notifications.success('Quote updated — pricing recalculated.');
            this.closeEdit();
            await this.refresh();
        } catch (e) {
            this.notifications.error(e.message || 'Failed to update quote.');
        } finally {
            this.isSavingEdit = false;
        }
    }
}
