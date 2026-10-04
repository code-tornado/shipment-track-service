# How Suppli would connect

Stock and orders live in Suppli; this service owns the shipping leg. The two
systems never share a database. Everything crosses the boundary through the
REST API (Suppli → service) and signed webhooks (service → Suppli).

```
   Suppli                                   Shipment tracking service
   ──────                                   ─────────────────────────
   order confirmed / proforma invoice ──▶   PUT  /customers, PUT /products   (reference data)
   booked for shipping                      POST /shipments { containers, cargo_items }
   packing slip issued (tags, NP/DF)  ──▶   POST /containers/{id}/cargo-items
                                            PATCH /cargo-items/{id}          (reference corrections)

   receives status / date events      ◀──   POST <suppli endpoint>  X-Webhook-Signature: sha256=…
                                            shipment.status_changed · shipment.dates_changed
                                            cargo_item.dates_changed
   can always re-read the truth       ──▶   GET /shipments/{id}, GET /shipments/{id}/history,
                                            GET /lookup?q=<invoice|tag|container>
```

## 1. Authentication

* Every API request carries a bearer token (`Authorization: Bearer <token>`).
  Tokens are Laravel Sanctum personal access tokens, stored hashed.
* A token belongs to a **user**, and a user belongs to exactly one **company**.
  All reads and writes are scoped to that company by a global query scope, so a
  token can never see or touch another company's shipments, even by guessing ids.
* Suppli gets a dedicated **service user** per company (e.g. `suppli@<company>`)
  with its own token, created with `POST /auth/token` or by an admin. Revoking it
  (`DELETE /auth/token`) cuts Suppli off without touching human users.
* Recommended for production: an expiry on integration tokens (Sanctum
  `expiration`), IP allow-listing of Suppli's egress, TLS only. If Suppli already
  uses OAuth2 client credentials, the token endpoint is the natural place to
  plug that in; the per-company scoping stays the same.

## 2. How Suppli sends its data

Suppli is the source of truth for the fields the service only references:
proforma invoice no, exporter's reference (sales order no), tag numbers, NP/DF
product type, storage facility, customer and product master data. The service
never pulls from Suppli's database; Suppli pushes when something happens.

| Suppli event | Call | Notes |
|---|---|---|
| Customer / product created or renamed | `PUT /customers`, `PUT /products` | Idempotent upserts keyed by `suppli_ref` / `material_code`. Can also run as a nightly full sync. |
| Proforma invoice booked for shipping | `POST /shipments` | One call with the sailing (method, line, ports, ETD, planned ETA) and its containers. Cargo items can be sent in the same call or later. |
| Packing slip issued | `POST /containers/{id}/cargo-items` | One item per tag: `proforma_invoice_no`, `exporter_ref`, `tag_no`, `package_no`, product (`net_type` = `net_pen` / `dead_fish_collector` …), customer, weights. The same tag may appear on an NP net and its DF collector. |
| Reference corrected in Suppli | `PATCH /cargo-items/{id}` | Only reference fields; status and dates stay with this service. |
| Historic data (one-off) | `POST /imports` or `php artisan import:delivery-plan` | The Excel delivery plan; rows that cannot be imported are reported per row. |

Validation on the way in: container numbers must be valid ISO 6346 (air
waybills for air freight), ETD ≤ ETA, package numbers unique per company. A
`422` response lists every failing field, so Suppli can show the error next to
the order.

Idempotency: `package_no` (the packing-slip package number) is the natural key
of a cargo item. Suppli should send it; a repeated `POST` with the same package
number is rejected as a duplicate, so a retry after a timeout cannot create two
nets.

## 3. How the service notifies Suppli

Suppli registers an endpoint once per company:

```http
POST /api/v1/webhooks
{ "url": "https://suppli.example/hooks/shipping", "secret": "…", "events": ["*"] }
```

Every status change and every date change then produces a delivery:

```http
POST https://suppli.example/hooks/shipping
X-Webhook-Event: shipment.status_changed
X-Webhook-Delivery: 8123
X-Webhook-Signature: sha256=<HMAC-SHA256(raw body, secret)>

{
  "event": "shipment.status_changed",
  "occurred_at": "2026-09-29T10:14:00+00:00",
  "company_id": 1,
  "data": {
    "shipment": {
      "reference": "SHP-2026-0012", "status": "arrived",
      "etd": "2026-08-08", "eta": "2026-09-17", "ata": "2026-09-29", "days_delayed": 12,
      "containers": [
        { "container_no": "HLBU8324720", "proforma_invoice_nos": ["862600578"], "tag_nos": ["GNP-2606018", "GNP-2606019"] }
      ]
    },
    "change": { "from": "in_transit", "to": "arrived", "note": "Docked in Bergen", "actor": "Tom Andersen", "source": "user" }
  }
}
```

`shipment.dates_changed` and `cargo_item.dates_changed` carry the list of
changed fields with old value, new value, reason and actor, so Suppli can show
"ETA moved from 17 Sep to 29 Sep — vessel rerouted" next to the order without
calling back.

Delivery rules:

* Suppli verifies the signature and answers `2xx` quickly; processing happens
  asynchronously on Suppli's side.
* Non-2xx or no answer → retried with back-off (30 s, 2 min, 10 min, 1 h, then
  given up). Every attempt is logged (`GET /webhooks/{id}/deliveries`), so a
  missed event can be found and replayed.
* Events are **at-least-once**: Suppli should treat `X-Webhook-Delivery` as an
  idempotency key.
* Events carry the proforma invoice and tag numbers, which is what Suppli keys
  on; the shipment reference is the service's own id.
* If Suppli prefers polling, the same facts are available from
  `GET /shipments/{id}/history` and `GET /lookup?q=<invoice>`; adding an
  `updated_since` filter to `GET /shipments` is a small change.

## 4. Assumptions

1. **One shipment is one sailing** (same shipping method, line, destination
   port, ETD and planned ETA). It can hold several containers, and a container
   can carry cargo for several customers; that is what the delivery plan shows.
   If one container is held up separately it is split into its own shipment.
2. **Status is per shipment, delivery is per net.** The shipment moves
   `planned → in_transit → arrived → in_storage → delivered`; each cargo item has
   its own agreed customer delivery date and actual delivery date, because nets
   leave the warehouse one at a time. A shipment is "delivered" when all items are out.
3. **Two kinds of delay** are computed, never typed in: transit delay (actual or
   expected arrival vs planned ETA) and delivery delay (actual vs agreed customer date).
4. **"In Stock" in the Excel sheet means "in storage, not sold yet"**: it is
   imported as `in_storage` with `is_stock = true` on the cargo item.
5. **The delivery plan's "Arrival date / Actual ETA" column** is the arrival
   date for arrived shipments and a revised ETA for shipments still at sea. The
   revision is imported as an audited ETA change so the original plan stays visible.
6. **Tags are not unique**: the packing slip shows a net pen and its dead fish
   collector sharing one tag number. The package number is the unique key.
7. **Customers and products are matched by name / material code**
   case-insensitively; Suppli ids (`suppli_ref`) can be attached later without
   re-creating anything.
8. **Suppli pushes, the service never pulls** from Suppli, and there is no
   direct database access in either direction.
9. **Tripletex / NOFI / transport-cost columns** of the sheet are out of scope
   (they belong to invoicing and the service station), so they are not imported.
10. **One company = one tenant.** Users and integrations of a company share its
    data; there is no finer-grained permission model yet.

## 5. Open questions for Suppli

* Does Suppli have stable ids for customers and products we should store as
  `suppli_ref` from day one?
* Which event in Suppli marks an invoice as "booked for shipping" (to trigger
  `POST /shipments`), and does Suppli know container numbers at that point or
  only after the shipping line confirms?
* Should "delivered" in this service close the order line in Suppli
  automatically, or only notify?
