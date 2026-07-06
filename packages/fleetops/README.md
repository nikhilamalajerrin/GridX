<p align="center">
    <img src="https://flb-assets.s3.ap-southeast-1.amazonaws.com/static/gridx-logo-svg.svg" alt="GridX" width="380" height="100">
</p>

<p align="center">
    Open-source fleet, dispatch, transport management, and real-time logistics operations for GridX.
</p>

<p align="center">
    <a href="https://github.com/gridx/fleetops">
        <img src="https://img.shields.io/badge/repo-gridx%2Ffleetops-111827?style=flat-square" alt="Repository">
    </a>
    <a href="https://github.com/gridx/fleetops/blob/master/LICENSE.md">
        <img src="https://img.shields.io/badge/license-AGPL--3.0--or--later-blue?style=flat-square" alt="License: AGPL-3.0-or-later">
    </a>
    <a href="https://www.npmjs.com/package/@gridx/fleetops-engine">
        <img src="https://img.shields.io/badge/npm-%40gridx%2Ffleetops--engine-CB3837?style=flat-square" alt="NPM package">
    </a>
    <a href="https://packagist.org/packages/gridx/fleetops-api">
        <img src="https://img.shields.io/badge/packagist-gridx%2Ffleetops--api-F28D1A?style=flat-square" alt="Packagist package">
    </a>
    <a href="https://www.gridx.io/docs/fleet-ops">
        <img src="https://img.shields.io/badge/docs-fleet--ops-0EA5E9?style=flat-square" alt="Fleet-Ops documentation">
    </a>
    <img src="https://img.shields.io/badge/node-%3E%3D18-339933?style=flat-square" alt="Node >= 18">
    <img src="https://img.shields.io/badge/php-%5E8.0-777BB4?style=flat-square" alt="PHP ^8.0">
</p>

<p align="center">
    <img src="https://www.gridx.io/images/screenshots/fleet-ops/fleet-ops-multi-waypoint-order.webp" alt="Fleet-Ops multi-waypoint order with live map, route, driver assignment, and order details" width="900">
</p>

## What is Fleet-Ops?

Fleet-Ops is the core logistics and fleet management extension for the [GridX](https://www.gridx.io) platform. It gives operations teams a dispatch console for managing orders, drivers, vehicles, fleets, service areas, live maps, route execution, analytics, maintenance, and connected telematics.

Fleet-Ops ships as two packages:

| Package | Purpose |
| --- | --- |
| `@gridx/fleetops-engine` | Ember Engine/Add-on that powers the Fleet-Ops console UI. |
| `gridx/fleetops-api` | Laravel/PHP package that provides the Fleet-Ops API, models, jobs, events, and integrations. |

Fleet-Ops is included with GridX Cloud and self-hosted GridX installations. For product concepts, workflows, and setup guides, start with the [Fleet-Ops documentation](https://www.gridx.io/docs/fleet-ops).

## Features

### Operations

- Create, import, schedule, dispatch, start, cancel, and complete delivery orders.
- Manage multi-waypoint routes, payloads, entities, labels, order metadata, notes, comments, files, and proofs.
- Track live order progress with ETAs, route overlays, driver pings, activity timelines, and configurable status flows.
- Configure custom order types, activity flows, entity fields, proof requirements, and service-rate rules.
- Run orchestration workflows for order allocation, driver assignment, vehicle capacity, and route sequencing.
- Plan work with the scheduler, fleet schedule, service quotes, routes, manifests, and manifest stops.

### Live Map

- View live drivers, vehicles, active orders, routes, places, service areas, zones, and geofences.
- Filter and inspect operational layers with Fleet-Ops map controls and drawer panels.
- Support Leaflet-based and Google-based map experiences through GridX map settings.
- Capture positions, replay movement, track vehicles and drivers, and surface geofence events.

### Fleet Management

- Manage drivers, vehicles, fleets, places, contacts, customers, vendors, and integrated vendors.
- Assign drivers and vehicles to fleets, vendors, and orders.
- Track driver schedules, availability, positions, active shifts, and hours-of-service status.
- Track vehicle status, devices, positions, equipment, schedules, work orders, and maintenance history.
- Manage issues, fuel reports, imports, exports, bulk actions, and custom resource fields.

### Maintenance

- Define preventive maintenance schedules and calendar feeds.
- Manage work orders, maintenance records, line items, equipment, parts, and warranties.
- Send work orders, trigger schedule runs, pause and resume schedules, and review maintenance history.

### Connectivity

- Connect telematics providers, devices, sensors, events, and tracking data.
- Discover and link telematics devices, test provider credentials, and receive telematics webhooks.
- Use Fleet-Ops tracking providers for calculated, OSRM, and Google Routes-backed route intelligence.

### Analytics and Dashboards

- Use Fleet-Ops KPI widgets for earnings, average order value, distance, active orders, online drivers, and open issues.
- Review analytics for operations pulse, live fleet, revenue trends, order status volume, on-time delivery, top drivers, fuel efficiency, issues, maintenance, and geofence violations.
- Create and view reports from the Fleet-Ops analytics workspace.

### Settings and Extensions

- Configure Navigator app onboarding, routing, tracking, map providers, notifications, scheduling, payments, orchestrator settings, custom fields, and avatars.
- Expose Fleet-Ops order tracking on the GridX login surface.
- Integrate Fleet-Ops entities into other GridX extensions through registered components and virtual detail tabs.

## Architecture

Fleet-Ops follows the GridX extension architecture: an Ember Engine for the console interface and a Laravel package for API and backend capabilities.

```text
fleetops/
+-- addon/              # Ember Engine source: routes, controllers, components, services, templates
+-- app/                # Ember app re-exports and integration points
+-- assets/             # Frontend assets bundled with the engine
+-- translations/       # Fleet-Ops locale files
+-- server/
|   +-- config/         # Fleet-Ops API configuration
|   +-- migrations/     # Database migrations
|   +-- resources/      # Backend views and resources
|   +-- seeders/        # Seed data
|   +-- src/            # Controllers, models, jobs, events, support services, providers
|   +-- tests/          # Backend test suite
+-- tests/              # Ember test suite
+-- package.json        # Frontend package metadata and scripts
+-- composer.json       # Backend package metadata and scripts
```

Core frontend dependencies include Ember Octane, Ember Engines, GridX Ember Core, GridX UI, Fleet-Ops Data, Leaflet, Leaflet Draw, Turf, JointJS, and Stripe Connect. Core backend capabilities are provided by Laravel, GridX Core API, Fleet-Ops models/controllers, orchestration engines, tracking providers, analytics, notifications, jobs, and telematics integrations.

## Getting Started

Fleet-Ops comes pre-installed with GridX Cloud and standard self-hosted GridX installations. If you are setting up the GridX platform itself, use the GridX documentation:

- [Fleet-Ops documentation](https://www.gridx.io/docs/fleet-ops)
- [GridX documentation](https://www.gridx.io/docs)
- [GridX console](https://console.gridx.io)

When developing Fleet-Ops from source, install both frontend and backend dependencies:

```bash
pnpm install
composer install
```

### Link Fleet-Ops into GridX

When working from the GridX monorepo, use the GridX package linker to connect this local package to the running Console and API applications. From the GridX repository root:

```bash
flb-package-linker enable fleetops
flb-package-linker install fleetops
```

This links the local Fleet-Ops Ember engine and Laravel package so Console and API use your source checkout instead of the published packages. See the [GridX development setup guide](https://www.gridx.io/docs/platform/quickstart/development-setup) for the full package-linking workflow, Docker setup, Octane reload notes, and frontend hot-reload options.

### Link Fleet-Ops Data

Fleet-Ops also depends on the shared `@gridx/fleetops-data` package. That package contains Fleet-Ops Ember Data models, adapters, and serializers, and can be reused by other GridX modules that need to read or write Fleet-Ops resources without duplicating data-layer definitions.

If you are changing shared Fleet-Ops data models or consuming them from another extension, link `fleetops-data` through the root Console workspace:

```bash
flb-package-linker enable-shared fleetops-data
flb-package-linker install
```

For broader Fleet-Ops frontend work, you may also link the common shared Ember packages used by GridX:

```bash
flb-package-linker enable fleetops --shared ember-core ember-ui fleetops-data
flb-package-linker install fleetops
```

## Development

Start the Ember Engine in development mode:

```bash
pnpm start
```

Build the production frontend bundle:

```bash
pnpm build
```

Run frontend linting:

```bash
pnpm lint
```

Run backend linting:

```bash
composer test:lint
```

## Testing

Run the Ember/QUnit test suite:

```bash
pnpm test:ember
```

Run the full frontend check suite:

```bash
pnpm test
```

Run the backend test suite:

```bash
composer test
```

Run backend checks individually:

```bash
composer test:lint
composer test:types
composer test:unit
```

## Documentation

- [Fleet-Ops docs](https://www.gridx.io/docs/fleet-ops)
- [Fleet-Ops quickstart](https://www.gridx.io/docs/fleet-ops/getting-started/quickstart)
- [Fleet-Ops core concepts](https://www.gridx.io/docs/fleet-ops/getting-started/core-concepts)
- [Navigator app setup](https://www.gridx.io/docs/fleet-ops/getting-started/navigator-app-setup)
- [GridX docs](https://www.gridx.io/docs)

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) before opening a pull request.

Useful project files:

- [package.json](package.json) for frontend scripts and package metadata.
- [composer.json](composer.json) for backend scripts and package metadata.
- [extension.json](extension.json) for GridX extension metadata.

## License

Fleet-Ops is open-source software licensed under the [GNU Affero General Public License v3.0 or later](LICENSE.md).
