import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class QuotesRoute extends Route {
    @service quotes;

    async model() {
        return this.quotes.fetchAll();
    }
}
