import { module, test } from 'qunit';

import { setupTest } from '@gridx/console/tests/helpers';

module('Unit | Model | chat log', function (hooks) {
    setupTest(hooks);

    // Replace this with your real tests.
    test('it exists', function (assert) {
        let store = this.owner.lookup('service:store');
        let model = store.createRecord('chat-log', {});
        assert.ok(model);
    });
});
