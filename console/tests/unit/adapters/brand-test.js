import { module, test } from 'qunit';
import { setupTest } from '@gridx/console/tests/helpers';

module('Unit | Adapter | brand', function (hooks) {
    setupTest(hooks);

    // Replace this with your real tests.
    test('it exists', function (assert) {
        let adapter = this.owner.lookup('adapter:brand');
        assert.ok(adapter);
    });
});
