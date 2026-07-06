<p align="center">
    <p align="center">
        <img src="https://user-images.githubusercontent.com/58805033/191936702-fed04b0f-7966-4041-96d0-95e27bf98248.png" width="280" height="80" style="height: 80px;" />
    </p>
    <p align="center">
        Internal Bridge between GridX API and Extensions Registry
    </p>
</p>

---

## Overview

This monorepo contains both the frontend and backend components of the Internal Registry Bridge for GridX. The frontend is built using Ember.js and the backend is implemented in PHP.

* PHP 7.3.0 or above
* Ember.js v4.8 or above
* Ember CLI v4.8 or above
* Node.js v18 or above

## Structure

```
├── addon
├── app
├── assets
├── translations
├── config
├── node_modules
├── server
│ ├── config
│ ├── data
│ ├── migrations
│ ├── resources
│ ├── src
│ ├── tests
│ └── vendor
├── tests
├── testem.js
├── index.js
├── package.json
├── phpstan.neon.dist
├── phpunit.xml.dist
├── pnpm-lock.yaml
├── ember-cli-build.js
├── composer.json
├── CONTRIBUTING.md
├── LICENSE.md
├── README.md
```

## Installation

### Backend

Install the PHP packages using Composer:

```bash
composer require gridx/core-api
composer require gridx/registry-bridge
```
### Frontend

Install the Ember.js Engine/Addon:

```bash
pnpm install @gridx/registry-bridge-engine
```

## Usage

### Backend

🧹 Keep a modern codebase with **PHP CS Fixer**:
```bash
composer lint
```

⚗️ Run static analysis using **PHPStan**:
```bash
composer test:types
```

✅ Run unit tests using **PEST**
```bash
composer test:unit
```

🚀 Run the entire test suite:
```bash
composer test
```

### Frontend

🧹 Keep a modern codebase with **ESLint**:
```bash
pnpm lint
```

✅ Run unit tests using **Ember/QUnit**
```bash
pnpm test
pnpm test:ember
pnpm test:ember-compatibility
```

🚀 Start the Ember Addon/Engine
```bash
pnpm start
```

🔨 Build the Ember Addon/Engine
```bash
pnpm build
```

## Contributing
See the Contributing Guide for details on how to contribute to this project.

## License
This project is licensed under the MIT License.
