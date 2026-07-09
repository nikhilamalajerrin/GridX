import { debug } from '@ember/debug';

/**
 * One-time migration: GridX switched its default theme from dark to light.
 * Existing browser sessions have 'dark' cached in the per-user local-storage
 * option (set the first time they ever loaded the app), which always wins
 * over the platform default — so switching the platform default alone does
 * nothing for anyone who already has a cached preference.
 *
 * This runs once per browser (gated by a plain localStorage flag, separate
 * from the ember-local-storage-managed user options), forces the theme to
 * light via the real currentUser/theme services, then gets out of the way.
 * Users who explicitly pick dark afterward are respected — this never runs
 * again once the flag is set.
 */
const MIGRATION_FLAG = 'gridx_theme_migration_v1';

export function initialize(appInstance) {
    if (typeof window === 'undefined' || window.localStorage.getItem(MIGRATION_FLAG)) {
        return;
    }

    try {
        const currentUser = appInstance.lookup('service:current-user');
        const theme = appInstance.lookup('service:theme');

        currentUser.setOption('theme', 'light');
        theme.activeTheme = 'light';

        debug('[GridX Theme Migration] Forced default theme to light (one-time)');
    } catch (e) {
        debug(`[GridX Theme Migration] Skipped: ${e.message}`);
    } finally {
        window.localStorage.setItem(MIGRATION_FLAG, 'true');
    }
}

export default {
    initialize,
};
