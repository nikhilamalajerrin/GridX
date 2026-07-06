/* eslint-env node */
'use strict';
const { name, gridx } = require('../package');

module.exports = function (environment) {
    let ENV = {
        modulePrefix: name,
        environment,
        mountedEngineRoutePrefix: getMountedEngineRoutePrefix(),

        defaultValues: {
            categoryImage: getenv('DEFAULT_CATEGORY_IMAGE', 'https://flb-assets.s3.ap-southeast-1.amazonaws.com/images/fallback-placeholder-1.png'),
            placeholderImage: getenv('DEFAULT_PLACEHOLDER_IMAGE', 'https://flb-assets.s3.ap-southeast-1.amazonaws.com/images/fallback-placeholder-2.png'),
        },

        'ember-leaflet': {
            excludeCSS: true,
            excludeJS: true,
            excludeImages: true,
        },
    };

    return ENV;
};

function getMountedEngineRoutePrefix() {
    let mountedEngineRoutePrefix = 'storefront';
    if (gridx && typeof gridx.route === 'string') {
        mountedEngineRoutePrefix = gridx.route;
    }

    return `console.${mountedEngineRoutePrefix}`;
}

function getenv(variable, defaultValue = null) {
    return process.env[variable] !== undefined ? process.env[variable] : defaultValue;
}
