<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['webhook_endpoint_id', 'event', 'payload', 'status', 'response_code', 'attempts', 'last_attempt_at'])]
class WebhookDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_attempt_at' => 'immutable_datetime',
        ];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
