import loadExtensions from './load-extensions';
import gridxApiFetch from './gridx-api-fetch';
import isAuthenticated from './is-authenticated';

export default async function loadInstalledExtensions(additionalCoreEngines = []) {
    const CORE_ENGINES = [
        '@gridx/fleetops-engine',
        '@gridx/storefront-engine',
        '@gridx/registry-bridge-engine',
        '@gridx/dev-engine',
        '@gridx/iam-engine',
        '@gridx/ledger-engine',
        '@gridx/pallet-engine',
        '@gridx/ai-engine',
        '@gridx/customer-portal-engine',
        '@gridx/vroom-engine',
        '@gridx/valhalla-engine',
        ...additionalCoreEngines,
    ];
    const INDEXED_ENGINES = await loadExtensions();
    // const INSTALLED_ENGINES = await gridxApiFetch('get', 'engines', {}, { namespace: '~registry/v1', fallbackResponse: [] });

    let INSTALLED_ENGINES = [];
    if (isAuthenticated()) {
        INSTALLED_ENGINES = await gridxApiFetch('GET', 'engines', {}, { namespace: '~registry/v1', fallbackResponse: [] });
    }

    const isInstalledEngine = (engineName) => {
        return CORE_ENGINES.includes(engineName) || INSTALLED_ENGINES.find((pkg) => pkg.name === engineName);
    };

    return INDEXED_ENGINES.filter((pkg) => {
        return isInstalledEngine(pkg.name);
    });
}
