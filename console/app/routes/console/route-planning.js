import Route from '@ember/routing/route';
import { inject as service } from '@ember/service';

export default class RoutePlanningRoute extends Route {
    @service routePlanning;

    async model() {
        const [quotes, vehicles] = await Promise.all([this.routePlanning.fetchPaidQuotes(), this.routePlanning.fetchVehicles()]);

        return { quotes, vehicles };
    }
}
