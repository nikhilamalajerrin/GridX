'use strict';
const { name, gridx } = require('../package');

module.exports = function (environment) {
    let ENV = {
        modulePrefix: name,
        environment,
        mountedEngineRoutePrefix: getMountedEngineRoutePrefix(),
    };

    return ENV;
};

function getMountedEngineRoutePrefix() {
    let mountedEngineRoutePrefix = 'iam';
    if (gridx && typeof gridx.route === 'string') {
        mountedEngineRoutePrefix = gridx.route;
    }

    return `console.${mountedEngineRoutePrefix}`;
}
