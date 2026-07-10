/* eslint-env node */
'use strict';
const { name, gridx } = require('../package');

module.exports = function (environment) {
    let ENV = {
        modulePrefix: name,
        environment,
        mountedEngineRoutePrefix: getMountedEngineRoutePrefix(),

        'ember-leaflet': {
            excludeCSS: true,
            excludeJS: true,
            excludeImages: true,
        },
    };

    return ENV;
};

function getMountedEngineRoutePrefix() {
    let mountedEngineRoutePrefix = 'developers';
    if (gridx && typeof gridx.route === 'string') {
        mountedEngineRoutePrefix = gridx.route;
    }

    return `console.${mountedEngineRoutePrefix}`;
}
