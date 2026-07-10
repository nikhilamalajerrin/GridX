# GridX

**AI-powered fleet management for trucking companies in Saudi Arabia and the UAE.**

GridX is a white-labeled, AI-augmented fleet management platform built on top of the
[Fleetbase](https://github.com/fleetbase/fleetbase) open-source stack. It gives fleet
operators live vehicle tracking, a WhatsApp AI booking agent, multi-warehouse route
planning (VROOM + OSRM), VAT-compliant quoting/invoicing, and a driver mobile app —
all under one brand.

## Modules

| Path | What it is | Stack |
|---|---|---|
| [`platform/`](platform/) | Fleet operator console + backend API (orders, quotes, route planning, live tracking) | Ember.js console, Laravel API, MySQL, Redis |
| [`agent/`](agent/) | WhatsApp/email AI agent that turns freight requests into priced quotes and orders | Python, FastAPI, Claude/OpenRouter (LLM tool use) |
| [`driver-app/`](driver-app/) | Driver-facing mobile app (accept jobs, navigate, upload POD) | React Native (fork of Fleetbase's navigator-app) |
| [`landing/`](landing/) | Marketing/landing site | Static HTML |

## Architecture

```
Customer (WhatsApp) ──▶ agent/ (LLM tool use) ──▶ platform/api (quotes, orders)
                                                          │
                                                          ▼
                                          platform/console (dispatcher UI)
                                                          │
                                    ┌─────────────────────┼─────────────────────┐
                                    ▼                     ▼                     ▼
                          driver-app (mobile)     VROOM/OSRM (routing)   Traccar (GPS)
```

- **`platform/`** is the core: the Ember.js console dispatchers use, the Laravel API
  everything else talks to, and the self-hosted VROOM/OSRM services used for
  multi-warehouse route optimization on real GCC road data.
- **`agent/`** is a standalone FastAPI service. It receives WhatsApp/email messages,
  uses an LLM with tool access to price a job and create a quote against `platform/api`,
  and replies to the customer — no direct database access, everything goes through the API.
- **`driver-app/`** and **`landing/`** are independent apps that also talk to `platform/api`.

## Prerequisites

- Docker + Docker Compose (for `platform/`)
- Node.js 18+ and Yarn (for `driver-app/`)
- Python 3.10+ (for `agent/`)
- API keys: an LLM provider key (Anthropic or OpenRouter) for `agent/`, a Twilio account
  if you want real WhatsApp delivery instead of the `/simulate` test endpoint

## Running the platform (console + API)

```bash
cd platform
cp api/.env.example api/.env   # fill in DB/mail/LLM provider credentials
docker-compose up -d
```

- Console: http://localhost:4200
- API: http://localhost:8000

See [`platform/README.md`](platform/README.md) for the full breakdown of services
(MySQL, Redis, SocketCluster, self-hosted VROOM/OSRM, Traccar).

## Running the AI booking agent

```bash
cd agent
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
cp .env.example .env   # set GRIDX_API_KEY (from platform) + your LLM provider key
uvicorn main:app --port 9000
```

Test without a real WhatsApp number:

```bash
curl -X POST localhost:9000/simulate -H 'content-type: application/json' -d '{
  "message": "Need a flatbed from Riyadh Central Warehouse to Dammam Logistics City, 24 tons cement, tomorrow morning",
  "sender": "+966501112222"
}'
```

See [`agent/README.md`](agent/README.md) for wiring up a real Twilio WhatsApp webhook.

## Running the driver app

```bash
cd driver-app
yarn install
cp .env.example .env   # point API_HOST at your platform API
yarn ios     # or: yarn android
```

## Running the landing page

`landing/index.html` is a static file — open it directly or serve it with any static
file server (`npx serve landing`).

## Contributing

Each module can be developed independently once `platform/api` is running locally,
since `agent/` and `driver-app/` only depend on it over HTTP. Start `platform/`
first, then bring up whichever other module you're working on.
