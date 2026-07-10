import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import config from 'ember-get-config';

export default class GridXAttributionComponent extends Component {
    @service modalsManager;

    licensingUrl = 'https://www.gridx.io';

    get disabled() {
        return config.APP?.disableGridXAttribution === true;
    }

    get appVersion() {
        return config.version ? `v${config.version}` : null;
    }

    @action openLegalNotice() {
        this.modalsManager.show('modals/fleetbase-legal-notice', {
            title: 'GridX Legal Notices',
            acceptButtonText: 'Done',
            acceptButtonIcon: 'check',
            hideDeclineButton: true,
            modalClass: 'modal-md gridx-legal-notice-modal',
        });
    }
}
