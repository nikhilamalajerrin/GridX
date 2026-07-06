# GridX

**AI-Powered Fleet Management for GCC**

GridX is an AI-powered fleet management platform for trucking companies in Saudi Arabia and the UAE, built on top of the Fleetbase open-source platform. It gives fleet operators live vehicle tracking, a WhatsApp AI agent for driver/customer communication, and automated VAT-compliant invoicing.

This repository contains:

| Path | Description |
|---|---|
| `console/` | Ember.js admin dashboard (the fleet operator console) |
| `packages/` | Ember addons that power the console (fleet ops, storefront, IAM, ledger, AI, etc.) |
| `api/` | Laravel backend API |
| `docker-compose.yml` | Local dev stack (MySQL, Redis, SocketCluster, console, API) |

The driver-facing mobile app lives in a sibling repo: `../gridx-driver-app` (React Native, cloned from Fleetbase's navigator-app). The marketing site lives in `../gridx-landing`.

## Running locally

```bash
git clone <this-repo-url> gridx
cd gridx
cp api/.env.example api/.env   # already created in this checkout — edit as needed
docker-compose up -d
```

This starts:
- **console** on [http://localhost:4200](http://localhost:4200)
- **API** (via `httpd`) on [http://localhost:8000](http://localhost:8000)
- MySQL on `3306`, Redis, SocketCluster on `38000`

After the containers are up, run migrations/seed the database once (first run only):

```bash
docker exec -it gridx-api php artisan migrate --seed
```

## Environment variables

Key variables in `api/.env`:

| Variable | Value |
|---|---|
| `APP_NAME` | `GridX` |
| `APP_URL` | `https://api.gridx.io` (use `http://localhost:8000` for local dev) |
| `MAIL_FROM_ADDRESS` | `noreply@gridx.io` |
| `MAIL_FROM_NAME` | `GridX` |
| `DB_DATABASE` | `gridx` |

Console build-time variables live in `console/environments/.env.development` and `.env.production` (`API_HOST`, `SOCKETCLUSTER_HOST`, etc.).

## Accessing console and API

- Console (browser UI): `http://localhost:4200` locally, `https://console.gridx.io` in production
- API: `http://localhost:8000` locally, `https://api.gridx.io` in production
- Default support contact: `support@gridx.io`

## Building the driver app

The driver app (`GridX Driver`, bundle ID `io.gridx.driver`) is a separate React Native project:

```bash
cd ../gridx-driver-app
cp .env.example .env   # already created in this checkout — edit as needed
yarn install

# iOS
cd ios && pod install && cd ..
yarn ios

# Android
yarn android
```

Set `FLEETBASE_HOST` in `gridx-driver-app/.env` to your API URL (`https://api.gridx.io` in production, `http://localhost:8000` for local dev against the docker stack).

## Brand

- Primary: `#1B4F8A` · Accent: `#F39C12`
- Domain: `gridx.io`
