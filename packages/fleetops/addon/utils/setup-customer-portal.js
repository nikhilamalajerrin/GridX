import CustomerAdminSettingsComponent from '../components/customer/admin-settings';

export default function setupCustomerPortal(_fleetopsEngine, universe) {
    const extensionManager = universe.getService('universe/extension-manager');
    const isCustomerPortalInstalled = extensionManager.isInstalled('@gridx/customer-portal-engine');
    if (!isCustomerPortalInstalled) return;

    // If customer portal already loaded just run setup
    if (extensionManager.isEngineLoaded('@gridx/customer-portal-engine')) {
        const customerPortalEngine = extensionManager.getEngineInstance('@gridx/customer-portal-engine');
        if (customerPortalEngine) {
            return setup(customerPortalEngine, universe);
        }
    }

    // Otherwise, wait for customer portal engine to load to run setup
    universe.onEngineLoaded('@gridx/customer-portal-engine', (customerPortalEngine) => {
        setup(customerPortalEngine, universe);
    });
}

function setup(customerPortalEngine, universe) {
    // If setup already completed don't run again
    if (customerPortalEngine?._fleetopsSetupCompleted === true) return;

    const registryService = universe.getService('universe/registry-service');

    // Register registries
    registryService.register('customer-portal:admin-settings', '@gridx/customer-portal-engine', CustomerAdminSettingsComponent);

    // Flag fleetops setup complete
    customerPortalEngine._fleetopsSetupCompleted = true;
}
