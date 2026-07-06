/**
 * register-template-resources
 *
 * Registers Ledger model classes as queryable resource types in the
 * TemplateBuilder query form. This runs once per application instance,
 * making Invoice, Transaction, Account, and Wallet available as data
 * sources when building invoice templates.
 */
export function initialize(appInstance) {
    const templateBuilder = appInstance.lookup('service:template-builder');
    if (!templateBuilder) {
        return;
    }
    templateBuilder.registerResourceTypes([
        {
            label: 'Invoice',
            value: 'GridX\\Ledger\\Models\\Invoice',
            icon: 'file-invoice-dollar',
        },
        {
            label: 'Transaction',
            value: 'GridX\\Ledger\\Models\\Transaction',
            icon: 'money-bill-transfer',
        },
        {
            label: 'Account',
            value: 'GridX\\Ledger\\Models\\Account',
            icon: 'building-columns',
        },
        {
            label: 'Wallet',
            value: 'GridX\\Ledger\\Models\\Wallet',
            icon: 'wallet',
        },
    ]);
}

export default { initialize };
