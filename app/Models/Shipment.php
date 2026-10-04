<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingMethod;
use App\Tenancy\BelongsToCompany;
use Carbon\CarbonImmutable;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'company_id', 'reference', 'status', 'shipping_method', 'shipping_line', 'origin_port',
    'destination_port', 'incoterm', 'etd', 'eta', 'ata', 'status_changed_at', 'notes', 'created_by',
])]
class Shipment extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'shipping_method' => ShippingMethod::class,
            'etd' => 'immutable_date',
            'eta' => 'immutable_date',
            'ata' => 'immutable_date',
            'status_changed_at' => 'immutable_datetime',
        ];
    }

    public function containers(): HasMany
    {
        return $this->hasMany(Container::class)->chaperone()->orderBy('id');
    }

    public function cargoItems(): HasManyThrough
    {
        return $this->hasManyThrough(CargoItem::class, Container::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(StatusHistory::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function dateChanges(): MorphMany
    {
        return $this->morphMany(DateChange::class, 'subject')->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasArrived(): bool
    {
        return $this->status->isAtLeast(ShipmentStatus::Arrived);
    }

    /**
     * Days the arrival is (or was) late compared to the planned ETA.
     * Arrived: actual arrival − ETA. Not arrived yet: today − ETA once the ETA has passed.
     */
    public function transitDelayDays(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();

        if ($this->hasArrived()) {
            return $this->ata === null ? 0 : max(0, (int) $this->eta->diffInDays($this->ata, false));
        }

        return max(0, (int) $this->eta->diffInDays($today, false));
    }

    public function isDelayed(?CarbonImmutable $today = null): bool
    {
        return $this->transitDelayDays($today) > 0;
    }

    /**
     * Next reference for a company, e.g. SHP-2026-0042.
     */
    public static function nextReference(int $companyId, ?int $year = null): string
    {
        $year ??= (int) date('Y');
        $prefix = sprintf('SHP-%d-', $year);

        $last = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $seq = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return sprintf('%s%04d', $prefix, $seq);
    }
}
