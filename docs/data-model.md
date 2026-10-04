# Data model

```
companies ─1:N─ users ─1:N─ personal_access_tokens        (Sanctum API tokens)
    │
    ├─1:N─ customers ───────────────────────────────┐
    ├─1:N─ products ──────────────────────────────┐ │
    │                                             │ │
    ├─1:N─ shipments ─1:N─ containers ─1:N─ cargo_items ─N:1─┘ │
    │          │                                    └──N:1──────┘
    │          └─1:N─ status_history
    │
    ├─1:N─ date_changes      (subject = shipment | cargo_item, polymorphic)
    ├─1:N─ import_runs ─1:N─ import_issues
    └─1:N─ webhook_endpoints ─1:N─ webhook_deliveries
```

Every business table carries `company_id`. The `BelongsToCompany` trait adds a
global scope (`WHERE company_id = <current company>`) and stamps the column on
create; the current company is set from the authenticated token by the
`company` middleware, or explicitly by console commands and jobs.

## Tables

| Table | Owner | What it holds |
|---|---|---|
| `companies` | service | Tenants. |
| `users` | service | People and integration accounts; `company_id`. Tokens via Sanctum. |
| `customers` | Suppli (reference) | `name`, `name_normalized` (unique per company, case/space-insensitive), `suppli_ref`. |
| `products` | Suppli (reference) | `material_code` (unique per company, nullable), `description`, `net_type` (`net_pen`, `dead_fish_collector`, `lice_shield`, …). |
| `shipments` | service | One sailing: `reference` (SHP-YYYY-NNNN), `status`, `shipping_method`, `shipping_line`, `origin_port`, `destination_port`, `incoterm`, `etd`, `eta` (planned/current), `ata` (actual arrival), `status_changed_at`, `notes`, `created_by`. |
| `containers` | service | `container_no` (ISO 6346, or AWB for air; unique per shipment), `seal_no`, `container_type`, `customs_cleared`, `storage_facility` (Suppli reference), `storage_date`, `pickup_date`. |
| `cargo_items` | mixed | One tagged net / panel / bundle. Suppli references: `proforma_invoice_no`, `exporter_ref`, `customer_po`, `tag_no`, `package_no` (unique per company), `customer_id`, `product_id`, quantities and weights, `is_stock`, `certificate_sent`. Owned by the service: `customer_delivery_date`, `delivered_at`. |
| `status_history` | service | One row per shipment status change: `from_status`, `to_status`, `note`, `source` (user/import/provider/system), `actor_id`, `actor_label`, `occurred_at`. |
| `date_changes` | service | Audit trail for `etd`, `eta`, `ata` (shipment) and `customer_delivery_date`, `delivered_at` (cargo item): `old_value`, `new_value`, `reason`, `source`, `actor_id`, `actor_label`, `occurred_at`. |
| `import_runs` / `import_issues` | service | One run per uploaded spreadsheet with counts; one issue per problem (`row_number`, `level` error/warning, `column`, `message`, `raw` snapshot). |
| `webhook_endpoints` / `webhook_deliveries` | service | Where to notify (URL, HMAC secret, subscribed events) and what was sent (payload, status, response code, attempts). |

## Rules encoded in the model

* **Status flow** (`App\Enums\ShipmentStatus`): `planned → in_transit → arrived → in_storage → delivered`, plus the shortcuts `in_transit → in_storage` and `arrived → delivered`. Nothing moves backwards; a correction is a new forward change with a note.
* **Dates**: ETA and actual arrival are never before ETD (validated on create and on every change).
* **Derived, never stored**: `days_delayed` on a shipment = `ata − eta` once arrived, else `today − eta` once the ETA has passed; on a cargo item = `delivered_at − customer_delivery_date`, or `today − customer_delivery_date` while overdue. A cargo item's `status` is `delivered` when `delivered_at` is set, otherwise its shipment's status.
* **Implied dates on status change**: `arrived` sets `ata` if empty; `in_storage` sets `storage_date` on containers that lack one; `delivered` sets `delivered_at` on every undelivered item. Each of these goes through the audit trail with the status change as reason.
* **Tags are not unique** (an NP net and its DF collector share one); `package_no` is the natural key and what makes re-imports idempotent.
