<?php

namespace App\Queries;

use App\Enums\ShipmentStatus;
use App\Models\CargoItem;
use App\Models\Shipment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filters for the shipment list. Every filter is optional and they combine with AND.
 */
class ShipmentQuery
{
    public const SORTABLE = ['etd', 'eta', 'ata', 'reference', 'status', 'destination_port', 'created_at'];

    /**
     * @param  Builder<Shipment>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Shipment>
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');

        if (isset($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : explode(',', $filters['status']);
            $query->whereIn('status', array_map('trim', $statuses));
        }

        if (isset($filters['shipping_method'])) {
            $query->where('shipping_method', $filters['shipping_method']);
        }

        if (isset($filters['destination_port'])) {
            $query->whereLike('destination_port', '%'.$filters['destination_port'].'%');
        }

        if (isset($filters['shipping_line'])) {
            $query->whereLike('shipping_line', '%'.$filters['shipping_line'].'%');
        }

        if (isset($filters['container_no'])) {
            $query->whereHas('containers', fn ($q) => $q->whereLike('container_no', '%'.$filters['container_no'].'%'));
        }

        if (isset($filters['proforma_invoice_no'])) {
            $query->whereHas('cargoItems', fn ($q) => $q->whereLike('proforma_invoice_no', '%'.$filters['proforma_invoice_no'].'%'));
        }

        if (isset($filters['tag_no'])) {
            $digits = preg_replace('/\D+/', '', (string) $filters['tag_no']) ?: $filters['tag_no'];
            $query->whereHas('cargoItems', fn ($q) => $q->whereLike('tag_no', '%'.$digits.'%'));
        }

        if (isset($filters['customer_id'])) {
            $query->whereHas('cargoItems', fn ($q) => $q->where('customer_id', $filters['customer_id']));
        }

        if (isset($filters['customer'])) {
            $query->whereHas('cargoItems.customer', fn ($q) => $q->whereLike('name', '%'.$filters['customer'].'%'));
        }

        if (isset($filters['product'])) {
            $query->whereHas('cargoItems.product', fn ($q) => $q
                ->whereLike('description', '%'.$filters['product'].'%')
                ->orWhereLike('material_code', '%'.$filters['product'].'%'));
        }

        foreach (['etd', 'eta'] as $field) {
            if (isset($filters[$field.'_from'])) {
                $query->whereDate($field, '>=', $filters[$field.'_from']);
            }
            if (isset($filters[$field.'_to'])) {
                $query->whereDate($field, '<=', $filters[$field.'_to']);
            }
        }

        if (! empty($filters['delayed']) && filter_var($filters['delayed'], FILTER_VALIDATE_BOOLEAN)) {
            self::delayed($query);
        }

        if (isset($filters['q'])) {
            self::search($query, (string) $filters['q']);
        }

        $sort = (string) ($filters['sort'] ?? '-etd');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');
        if (! in_array($column, self::SORTABLE, true)) {
            $column = 'etd';
            $direction = 'desc';
        }

        return $query->orderBy($column, $direction)->orderBy('id', $direction);
    }

    /**
     * Free-text search across everything a user might paste: reference,
     * container, invoice, tag, package, sales order, PO, customer, product.
     *
     * @param  Builder<Shipment>  $query
     */
    public static function search(Builder $query, string $term): void
    {
        $like = '%'.trim($term).'%';
        $digits = preg_replace('/\D+/', '', $term);

        $query->where(function (Builder $q) use ($like, $digits) {
            $q->whereLike('reference', $like)
                ->orWhereLike('shipping_line', $like)
                ->orWhereLike('destination_port', $like)
                ->orWhereHas('containers', fn ($c) => $c->whereLike('container_no', $like))
                ->orWhereHas('cargoItems', function ($i) use ($like, $digits) {
                    $i->whereLike('proforma_invoice_no', $like)
                        ->orWhereLike('exporter_ref', $like)
                        ->orWhereLike('customer_po', $like)
                        ->orWhereLike('package_no', $like)
                        ->orWhereLike('tag_no', $like);
                    if ($digits !== '') {
                        $i->orWhereLike('tag_no', '%'.$digits.'%');
                    }
                })
                ->orWhereHas('cargoItems.customer', fn ($c) => $c->whereLike('name', $like))
                ->orWhereHas('cargoItems.product', fn ($p) => $p->whereLike('description', $like)->orWhereLike('material_code', $like));
        });
    }

    /**
     * Arrived later than planned, or not arrived although the ETA has passed.
     *
     * @param  Builder<Shipment>  $query
     */
    public static function delayed(Builder $query, ?CarbonImmutable $today = null): Builder
    {
        $today ??= CarbonImmutable::today();

        return $query->where(function (Builder $q) use ($today) {
            $q->whereColumn('ata', '>', 'eta')
                ->orWhere(function (Builder $q) use ($today) {
                    $q->whereNull('ata')
                        ->whereIn('status', [ShipmentStatus::Planned, ShipmentStatus::InTransit])
                        ->whereDate('eta', '<', $today);
                });
        });
    }

    /**
     * Cargo items delivered late, or not delivered although the agreed date has passed.
     *
     * @param  Builder<CargoItem>  $query
     * @return Builder<CargoItem>
     */
    public static function lateDeliveries(Builder $query, ?CarbonImmutable $today = null): Builder
    {
        $today ??= CarbonImmutable::today();

        return $query->whereNotNull('customer_delivery_date')->where(function (Builder $q) use ($today) {
            $q->whereColumn('delivered_at', '>', 'customer_delivery_date')
                ->orWhere(fn (Builder $q) => $q->whereNull('delivered_at')->whereDate('customer_delivery_date', '<', $today));
        });
    }
}
