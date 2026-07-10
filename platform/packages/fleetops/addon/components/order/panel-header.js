import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';

export default class OrderPanelHeaderComponent extends Component {
    @service orderActions;

    @action
    editOrder() {
        this.orderActions.editOrderDetails(this.args.resource);
    }
}
