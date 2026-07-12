import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { tracked } from '@glimmer/tracking';

export default class GridxCopilotComponent extends Component {
    @service copilot;

    @tracked draft = '';

    // Plain <a href> nav (full page load, not an SPA transition — see the
    // note on the Quotes link below), so reading location once at render
    // time is safe: there's no client-side transition to miss.
    get isOnQuotesPage() {
        return window.location.pathname === '/quotes';
    }

    get isOnRoutePlanningPage() {
        return window.location.pathname === '/route-planning';
    }

    get isOnAiDispatchPage() {
        return window.location.pathname === '/ai-dispatch';
    }

    @action
    updateDraft(event) {
        this.draft = event.target.value;
    }

    @action
    toggle() {
        this.copilot.toggle();
    }

    @action
    submit(event) {
        event?.preventDefault();
        const question = this.draft.trim();
        if (!question || this.copilot.isLoading) {
            return;
        }

        this.draft = '';
        this.copilot.ask(question);
    }
}
