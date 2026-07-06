export default function getMountedEngineRoutePrefix(defaultName, gridx = {}) {
    let mountedEngineRoutePrefix = defaultName;
    if (gridx && typeof gridx.route === 'string') {
        mountedEngineRoutePrefix = gridx.route;
    }

    return `console.${mountedEngineRoutePrefix}.`;
}
