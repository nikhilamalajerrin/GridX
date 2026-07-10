import { module, test } from 'qunit';
import { setupRenderingTest } from 'dummy/tests/helpers';
import { render, triggerEvent, waitFor } from '@ember/test-helpers';
import { hbs } from 'ember-cli-htmlbars';

module('Integration | Component | docs-panel', function (hooks) {
    setupRenderingTest(hooks);

    hooks.beforeEach(function () {
        this.wormhole = document.createElement('div');
        this.wormhole.id = 'application-root-wormhole';
        document.body.appendChild(this.wormhole);
    });

    hooks.afterEach(function () {
        this.wormhole.remove();
    });

    test('it renders official docs in a right overlay', async function (assert) {
        const docsPanel = this.owner.lookup('service:docs-panel');
        docsPanel.open('https://www.gridx.io/docs/fleet-ops/orders', { title: 'Orders docs', theme: 'light' });

        await render(hbs`<DocsPanel />`);
        await waitFor('.gridx-docs-panel-overlay');

        assert.dom('.gridx-docs-panel-overlay').exists();
        assert.dom('.gridx-docs-panel-overlay iframe').hasAttribute('src', 'https://www.gridx.io/docs/fleet-ops/orders?embed=console&theme=light');
        assert.dom('.gridx-docs-panel-loading').exists();

        await triggerEvent('.gridx-docs-panel-overlay iframe', 'load');

        assert.dom('.gridx-docs-panel-loading').doesNotExist();
    });

    test('it renders fallback without a loading overlay for external urls', async function (assert) {
        const docsPanel = this.owner.lookup('service:docs-panel');
        docsPanel.open('https://example.com/help', { title: 'External docs' });

        await render(hbs`<DocsPanel />`);
        await waitFor('.gridx-docs-panel-overlay');

        assert.dom('.gridx-docs-panel-loading').doesNotExist();
        assert.dom('.gridx-docs-panel-overlay iframe').doesNotExist();
        assert.dom('.gridx-docs-panel-overlay').includesText('This page cannot be embedded here');
    });
});
