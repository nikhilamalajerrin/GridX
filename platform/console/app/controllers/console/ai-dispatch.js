import Controller from '@ember/controller';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { tracked } from '@glimmer/tracking';

/**
 * AI Dispatch: review what the WhatsApp/email booking agent decided and why,
 * before it takes effect. Every agent run opens a session (see
 * GridxAgentSession) and any quote it creates carries the agent's own
 * reasoning text — this page is the human-in-the-loop checkpoint the agent
 * was always designed around (it only ever creates a *quote*, never a live
 * order), now with the "why" visible instead of just the resulting price.
 */
export default class AiDispatchController extends Controller {
    @service fetch;
    @service quotes;
    @service notifications;

    @tracked busyId = null;
    @tracked editingQuote = null;
    @tracked editForm = null;
    @tracked isSavingEdit = false;

    get quotes() {
        return this.model.quotes;
    }

    get sessions() {
        return this.model.sessions;
    }

    // Pending decisions: quotes still awaiting a dispatcher call, newest first.
    get pendingDecisions() {
        return this.quotes.filter((q) => q.status === 'quote_pending').sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    }

    async runAction(quote, label, path) {
        this.busyId = quote.public_id;
        try {
            await this.fetch.post(`quotes/${quote.public_id}/${path}`);
            this.notifications.success(`Quote ${quote.public_id} ${label}.`);
            const result = await this.fetch.get('quotes');
            this.model = { ...this.model, quotes: result?.quotes ?? [] };
        } catch (err) {
            this.notifications.serverError(err);
        } finally {
            this.busyId = null;
        }
    }

    @action approve(quote) {
        this.runAction(quote, 'approved', 'approve');
    }

    @action reject(quote) {
        this.runAction(quote, 'rejected', 'reject');
    }

    @action async refresh() {
        const [sessionsResult, quotesResult] = await Promise.all([this.fetch.get('agent-sessions'), this.fetch.get('quotes')]);
        this.model = { sessions: sessionsResult?.sessions ?? [], quotes: quotesResult?.quotes ?? [] };
    }

    // The AI agent gets pickup/dropoff/truck-type/pricing wrong often enough
    // that dispatchers need to correct it inline here, not just approve or
    // reject blind — same edit flow as the Quotes page.
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
