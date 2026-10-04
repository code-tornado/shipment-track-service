<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Tenancy\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\CargoItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'company_id', 'container_id', 'customer_id', 'product_id', 'proforma_invoice_no', 'exporter_ref',
    'customer_po', 'tag_no', 'package_no', 'quantity', 'unit', 'net_weight_kg', 'gross_weight_kg',
    'is_stock', 'certificate_sent', 'customer_delivery_date', 'delivered_at', 'comments',
])]
class CargoItem extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<CargoItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'net_weight_kg' => 'decimal:2',
            'gross_weight_kg' => 'decimal:2',
            'is_stock' => 'boolean',
            'certificate_sent' => 'boolean',
            'customer_delivery_date' => 'immutable_date',
            'delivered_at' => 'immutable_date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CargoItem $item): void {
            foreach (['proforma_invoice_no', 'exporter_ref', 'customer_po', 'package_no'] as $field) {
                if ($item->$field !== null) {
                    $item->$field = trim((string) $item->$field) ?: null;
                }
            }
            $item->tag_no = self::normalizeTag($item->tag_no);
        });
    }

    /**
     * Tags appear as "2606018", "GNP-2606018" or "GLS - 2511017"; store PREFIX-NUMBER.
     */
    public static function normalizeTag(?string $tag): ?string
    {
        $tag = strtoupper(trim((string) $tag));
        if ($tag === '' || $tag === '-') {
            return null;
        }
        if (preg_match('/^([A-Z]{2,4})?\s*-?\s*(\d{5,})$/', $tag, $m)) {
            return ($m[1] ?: 'GNP').'-'.$m[2];
        }

        return $tag;
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function shipment(): HasOneThrough
    {
        return $this->hasOneThrough(
            Shipment::class, Container::class, 'id', 'id', 'container_id', 'shipment_id'
        );
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function dateChanges(): MorphMany
    {
        return $this->morphMany(DateChange::class, 'subject')->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    /**
     * An item is "delivered" on its own once it has left the warehouse;
     * otherwise it is wherever its shipment is.
     */
    public function derivedStatus(?ShipmentStatus $shipmentStatus = null): ShipmentStatus
    {
        if ($this->isDelivered()) {
            return ShipmentStatus::Delivered;
        }

        return $shipmentStatus ?? $this->container->shipment->status;
    }

    /**
     * Days late against the agreed customer delivery date, null when no date was agreed.
     */
    public function deliveryDelayDays(?CarbonImmutable $today = null): ?int
    {
        if ($this->customer_delivery_date === null) {
            return null;
        }

        $today ??= CarbonImmutable::today();
        $actual = $this->delivered_at ?? $today;

        return max(0, (int) $this->customer_delivery_date->diffInDays($actual, false));
    }
}
