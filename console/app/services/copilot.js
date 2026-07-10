import Service, { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import config from 'ember-get-config';

/**
 * Talks to the GridX Copilot endpoint (natural-language Q&A over live fleet
 * data). Deliberately a plain fetch, not an ember-data adapter — this isn't
 * a CRUD resource, just a single request/response action.
 */
export default class CopilotService extends Service {
    @service session;

    @tracked isOpen = false;
    @tracked isLoading = false;
    @tracked messages = [];

    toggle() {
        this.isOpen = !this.isOpen;
    }

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

    async ask(question) {
        this.messages = [...this.messages, { role: 'user', content: question }];
        this.isLoading = true;

        const host = config.API.host;
        const namespace = config.API.namespace;

        try {
            const response = await fetch(`${host}/${namespace}/copilot/ask`, {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${this.authToken}`,
                },
                body: JSON.stringify({ question }),
            });

            const payload = await response.json();
            const answer = response.ok ? payload.answer : payload.error || 'Copilot request failed.';

            this.messages = [...this.messages, { role: 'assistant', content: answer }];
        } catch (e) {
            this.messages = [...this.messages, { role: 'assistant', content: 'Copilot is unreachable right now.' }];
        } finally {
            this.isLoading = false;
        }
    }
}
