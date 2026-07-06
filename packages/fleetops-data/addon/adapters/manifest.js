import ApplicationAdapter from '@gridx/ember-core/adapters/application';

export default class ManifestAdapter extends ApplicationAdapter {
    urlForQuery() {
        return `${this.host}/${this.namespace}/fleet-ops/v1/manifests`;
    }

    urlForFindRecord(id) {
        return `${this.host}/${this.namespace}/fleet-ops/v1/manifests/${id}`;
    }
}
