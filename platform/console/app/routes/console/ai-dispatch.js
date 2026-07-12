import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class AiDispatchRoute extends Route {
    @service fetch;

    async model() {
        const [sessionsResult, quotesResult] = await Promise.all([this.fetch.get('agent-sessions'), this.fetch.get('quotes')]);

        return {
            sessions: sessionsResult?.sessions ?? [],
            quotes: quotesResult?.quotes ?? [],
        };
    }
}
