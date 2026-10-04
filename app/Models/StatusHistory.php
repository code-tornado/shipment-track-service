<?php

namespace App\Models;

use App\Enums\ChangeSource;
use App\Enums\ShipmentStatus;
use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'shipment_id', 'from_status', 'to_status', 'note', 'source', 'actor_id', 'actor_label', 'occurred_at',
])]
class StatusHistory extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $table = 'status_history';

    protected function casts(): array
    {
        return [
            'from_status' => ShipmentStatus::class,
            'to_status' => ShipmentStatus::class,
            'source' => ChangeSource::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
