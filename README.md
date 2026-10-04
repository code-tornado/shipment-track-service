# Shipment Tracking Service

A standalone microservice for the shipping leg of aquaculture net orders: from
ETD in India, through the Norwegian port and the storage facility, to the
delivery date at the customer's site. It replaces the hand-kept Excel
"Delivery Plan". Orders, stock and invoicing stay in Suppli / Tripletex; this
service only keeps read-only references to them.

Laravel 13 (PHP 8.3) · PostgreSQL (SQLite for tests) · REST API with token
auth · React + TypeScript UI · Docker.

## What it does

| Requirement | Where |
|---|---|
| Manage shipments → containers → cargo items (tags), with validation (ISO 6346 container numbers, air waybill numbers for air freight, ETD ≤ ETA, unique package numbers) | `POST /shipments`, `/containers`, `/cargo-items` · `app/Rules/ContainerNumber.php` |
| Status flow `planned → in_transit → arrived → in_storage → delivered` with history | `POST /shipments/{id}/status` · `app/Enums/ShipmentStatus.php`, `app/Services/ShipmentStatusService.php` |
| Change ETD / ETA / arrival / customer delivery dates with an audit trail (old, new, who, when, reason) and days delayed | `PATCH /shipments/{id}/dates`, `PATCH /cargo-items/{id}/dates` · `app/Services/DateChangeService.php` |
| Reports: upcoming deliveries for a date range, status overview, delayed shipments | `GET /reports/*` |
| Lookup by container no, proforma invoice, tag, product or customer | `GET /lookup?q=…&type=…` |
| Import the Excel delivery plan, normalise it, report rows that cannot be imported | `POST /imports`, `php artisan import:delivery-plan` · `app/Import/` |
| Token authentication, data separated per company | Sanctum + `app/Tenancy/` |
| Web UI: shipment list with filters, shipment page (status + dates with history), lookup, reports, import | `resources/js/` |
| Bonus: provider interface so status/dates can come from an external API, with a fake used in tests | `app/Tracking/` |
| Outbound webhooks so Suppli learns about status and date changes | `app/Webhooks/` · [docs/suppli-integration.md](docs/suppli-integration.md) |

API documentation: [docs/openapi.yaml](docs/openapi.yaml), served as Swagger UI at
`/api/docs` when the app runs. Data model: [docs/data-model.md](docs/data-model.md).

## Quick start with Docker

```bash
docker compose up --build
```

Then open <http://localhost:8000>. On first start the container runs the
migrations and seeds two companies with the delivery plan from the task
imported into the first one:

| Company | Login (UI) | API token |
|---|---|---|
| Garware Technical Fibres AS (Norway) — 178 rows imported | `demo@garware.example` / `password` | `1\|demo-token-garware` |
| Demo Fish Farms AS — empty, shows data separation | `demo@fishfarms.example` / `password` | `2\|demo-token-other` |

```bash
curl -H "Authorization: Bearer 1|demo-token-garware" http://localhost:8000/api/v1/reports/status-overview
curl -H "Authorization: Bearer 1|demo-token-garware" "http://localhost:8000/api/v1/lookup?q=HLBU8324720"
```

The compose file runs PostgreSQL 16 and the app with `php artisan serve`
(fine for evaluation; a production deployment would put php-fpm/FrankenPHP
behind nginx and run a queue worker for webhooks).

## Running it without Docker

Requirements: PHP 8.3 with `pdo_sqlite` (or `pdo_pgsql`), `zip`, `gd`, `intl`,
`mbstring`; Composer; Node 22.

```bash
composer install
cp .env.example .env            # SQLite by default; see the PostgreSQL block in .env.example
php artisan key:generate
php artisan migrate --seed      # creates the demo companies and imports database/seed/Norway_delivery_plan.xlsx
npm install && npm run build    # or `npm run dev` for hot reload
php artisan serve               # http://localhost:8000
```

Useful commands:

```bash
php artisan test                                            # 48 tests, SQLite in memory
php artisan import:delivery-plan path/to/plan.xlsx --issues # import a spreadsheet, list warnings
php artisan tracking:sync                                   # pull updates from the configured tracking provider
vendor/bin/pint                                             # code style
```

## API in short

All endpoints live under `/api/v1`, take and return JSON, and require
`Authorization: Bearer <token>` except `POST /auth/token`.

| Method & path | Purpose |
|---|---|
| `POST /auth/token` · `GET /auth/me` · `DELETE /auth/token` | Issue / inspect / revoke a token |
| `GET /shipments` | List with filters (`q`, `status`, `destination_port`, `customer`, `container_no`, `proforma_invoice_no`, `tag_no`, `product`, `etd_from/to`, `eta_from/to`, `delayed`, `sort`, `page`) |
| `POST /shipments` · `GET/PATCH/DELETE /shipments/{id}` | Create (with nested containers and cargo items), read, update descriptive fields, delete |
| `POST /shipments/{id}/status` | Move to the next status (`{status, note, occurred_at}`) |
| `PATCH /shipments/{id}/dates` | Change `etd`, `eta`, `ata`, or the `customer_delivery_date` of all undelivered items — `reason` required |
| `GET /shipments/{id}/history` | Status history + date changes of the shipment and its items, newest first |
| `POST /shipments/{id}/containers` · `PATCH/DELETE /containers/{id}` | Containers |
| `POST /containers/{id}/cargo-items` · `PATCH/DELETE /cargo-items/{id}` · `PATCH /cargo-items/{id}/dates` | Cargo items and their delivery dates |
| `GET /lookup?q=&type=all\|container\|invoice\|tag\|product\|customer` | Lookup |
| `GET /reports/upcoming-deliveries?from&to` · `GET /reports/status-overview` · `GET /reports/delayed` | Reports |
| `GET/PUT /customers` · `GET/PUT /products` | Suppli reference data (upserts) |
| `GET/POST /imports` · `GET /imports/{id}` | Excel import and its row-level report |
| `GET/POST/DELETE /webhooks` · `GET /webhooks/{id}/deliveries` | Outbound notifications |

Business-rule violations (a transition that is not allowed, ETA before ETD)
return `422` with `message` and `errors`, exactly like validation errors.

## Model in two sentences

A **shipment** is one sailing (method, line, ports, ETD, planned ETA, actual
arrival) and owns the status; it has **containers** (ISO 6346 number, seal,
type, storage facility) which hold **cargo items**: one row per tagged net with
the Suppli references (proforma invoice, exporter's reference, tag, package
number, product, customer) and the two dates this service owns per item, the
agreed customer delivery date and the actual delivery date. Status is tracked
per shipment because the transit leg is shared; delivery is tracked per item
because nets leave the warehouse one by one. Full diagram in
[docs/data-model.md](docs/data-model.md).

Status flow:

```
planned → in_transit → arrived → in_storage → delivered
              └──────────────────────┘ (storage date known, arrival not logged separately)
                          └──────────────────────┘ (delivered straight from the port)
```

`arrived` fills in the actual arrival date, `in_storage` stamps the storage
date on the containers, `delivered` marks every remaining item delivered; each
of those implied dates goes through the same audit trail as a manual change.

Delays are computed, never typed in: transit delay = actual (or expected)
arrival − planned ETA; delivery delay = delivered at − agreed customer date.

## Importing the Excel delivery plan

`database/seed/Norway_delivery_plan.xlsx` is the sheet from the task. The
importer (`app/Import/DeliveryPlanImporter.php`) finds the header row by its
text, so column order does not matter, and treats one row as one cargo item.

What is normalised (imported with a *warning* where the data was ambiguous):

* Status spellings (`In Storage` / `In storage`, `In Transit`) and **`In Stock` /
  `In stock` → `in_storage` with the stock flag** on the item (also when the
  customer PO is "Stock").
* Customer names are matched case- and whitespace-insensitively
  (`GARWARE TECHNICAL FIBRES AS` = `Garware Technical Fibres AS`).
* Tags: `2606018`, `GNP-2606018` and `GLS - 2511017` become `GNP-2606018` /
  `GLS-2511017`; ` -` and empty cells mean "no tag" (warning). Duplicate tags
  across an NP net and its DF collector are kept.
* Air freight rows (shipping method "Air") are validated as air waybill numbers
  instead of container numbers.
* Missing material code → product matched by description (warning).
* "Arrival date / Actual ETA" is the arrival date for arrived shipments; for
  shipments still at sea it is recorded as an audited ETA revision, so the
  original plan stays visible in the history.
* Rows of the same container that disagree on the arrival date → earliest is
  used, warning on each row.
* Delivered rows without a delivery date fall back to the storage / arrival date (warning).
* Container, invoice, net-type and numeric cells are trimmed; Excel numbers lose their `.0`.

What is rejected (row reported as an *error* and skipped, everything else still
imported): empty customer, invoice, description, destination or container;
invalid container / AWB number; missing or unparsable ETD or ETA; ETD after
ETA; unknown status or shipping method; non-numeric quantity; a package number
that appears twice in the file. A file without the expected header row fails
as a whole with a clear message.

Re-importing the same file is safe: rows whose package number already exists
are skipped with a warning, new rows land on the shipment their container
already belongs to.

Seed result: 178 rows → 178 cargo items in 66 containers on 40 shipments, 0 errors, 93 warnings.

## Tracking provider (bonus)

`App\Tracking\TrackingProvider` is the port through which status and dates can
come from an external system instead of manual entry:

```php
interface TrackingProvider {
    public function name(): string;
    public function trackContainer(Container $c): ?TrackingUpdate;  // sea leg: status, ETA, arrival
    public function trackDelivery(CargoItem $i): ?DeliveryUpdate;   // last mile (Shipmondo): planned / delivered
}
```

`ManualTrackingProvider` (default) returns nothing; `FakeTrackingProvider` is
used by the tests; a Shipmondo or container-tracking adapter would be a third
implementation selected with `TRACKING_PROVIDER` in `.env`.
`php artisan tracking:sync` (meant for the scheduler) applies whatever the
provider reports through the normal status and date services, so provider
changes appear in the history with `source = provider`.

## Notifying Suppli

Register an endpoint with `POST /webhooks`; every status change and date change
is then POSTed as JSON with an HMAC-SHA256 signature, retried with back-off,
and logged. Payloads, security and the assumptions behind the integration are
in [docs/suppli-integration.md](docs/suppli-integration.md).

## Tests

```bash
php artisan test
```

`tests/Feature` covers token auth, company isolation, shipment CRUD and
validation, the status flow and its history, date changes and the audit trail,
reports, lookup, the import of the real delivery plan and of a file with bad
rows, the tracking provider with the fake implementation, and webhooks.
`tests/Unit` covers the ISO 6346 / AWB check digits and the status enum.
GitHub Actions runs the suite and Pint on every push.

## Project layout

```
app/
  Enums/          ShipmentStatus (flow), ShippingMethod, NetType, DateField, ChangeSource
  Tenancy/        CurrentCompany, CompanyScope, BelongsToCompany
  Services/       ShipmentService, ShipmentStatusService, DateChangeService, ReferenceResolver, Actor
  Rules/          ContainerNumber (ISO 6346 + air waybill check digits)
  Queries/        ShipmentQuery (list filters, delayed / late-delivery predicates)
  Import/         DeliveryPlanImporter, RowParser, ParsedRow
  Tracking/       TrackingProvider port, Manual / Fake providers, TrackingSyncService
  Webhooks/       NotifyWebhooks, WebhookDispatcher, SendWebhookDelivery
  Http/           Controllers/Api, Requests, Resources, Middleware/SetCurrentCompany
database/         migrations, factories, seeders, seed/Norway_delivery_plan.xlsx
resources/js/     React UI (pages: Shipments, Shipment, NewShipment, Lookup, Reports, Import, Login)
docs/             openapi.yaml, suppli-integration.md, data-model.md
tests/            Feature and Unit tests
```
