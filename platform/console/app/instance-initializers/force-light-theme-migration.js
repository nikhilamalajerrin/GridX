import { debug } from '@ember/debug';

/**
 * One-time migration: GridX switched its default theme from dark to light.
 * Existing browser sessions have 'dark' cached in the per-user local-storage
 * option (set the first time they ever loaded the app), which always wins
 * over the platform default — so switching the platform default alone does
 * nothing for anyone who already has a cached preference.
 *
 * `currentUser.setOption` writes under a per-user-scoped key
 * (`optionsPrefix = authenticatedOptionOwnerId || this.id || 'anon'`), which
 * is only correct *after* the real user has loaded — calling it eagerly at
 * app boot writes under the 'anon:' prefix instead, a silent no-op for the
 * page the authenticated user actually sees. So this waits for the
 * documented `user.loaded` event before touching anything.
 *
 * Gated by a plain localStorage flag, separate from the ember-local-storage-
 * managed user options, so it only ever runs once per browser. Users who
 * explicitly pick dark afterward are respected — this never runs again once
 * the flag is set.
 */
const MIGRATION_FLAG = 'gridx_theme_migration_v2';

function runMigration(appInstance) {
    if (typeof window === 'undefined' || window.localStorage.getItem(MIGRATION_FLAG)) {
        return;
    }

    try {
        const currentUser = appInstance.lookup('service:current-user');
        const theme = appInstance.lookup('service:theme');

        currentUser.setOption('theme', 'light');
        theme.activeTheme = 'light';

        debug('[GridX Theme Migration] Forced default theme to light (one-time, post user.loaded)');
    } catch (e) {
        debug(`[GridX Theme Migration] Failed: ${e.message}`);
        return; // don't set the flag — retry on next load
    }

    window.localStorage.setItem(MIGRATION_FLAG, 'true');
}

export function initialize(appInstance) {
    if (typeof window === 'undefined' || window.localStorage.getItem(MIGRATION_FLAG)) {
        return;
    }

    const currentUser = appInstance.lookup('service:current-user');

    // If the user is already loaded by the time this initializer runs, act now.
    if (currentUser?.id) {
        runMigration(appInstance);
        return;
    }

    // Otherwise wait for the real authenticated user before touching options.
    currentUser?.on('user.loaded', () => runMigration(appInstance));
}

export default {
    initialize,
};
