<?php

namespace App\Models;

use App\Tenancy\BelongsToCompany;
use Database\Factories\ContainerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'shipment_id', 'container_no', 'seal_no', 'container_type',
    'customs_cleared', 'storage_facility', 'storage_date', 'pickup_date',
])]
class Container extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ContainerFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'customs_cleared' => 'boolean',
            'storage_date' => 'immutable_date',
            'pickup_date' => 'immutable_date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Container $container): void {
            $container->container_no = strtoupper(trim($container->container_no));
            if ($container->seal_no !== null) {
                $container->seal_no = trim($container->seal_no) ?: null;
            }
        });
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function cargoItems(): HasMany
    {
        return $this->hasMany(CargoItem::class)->chaperone()->orderBy('id');
    }
}
