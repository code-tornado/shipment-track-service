<?php

namespace App\Http\Resources;

use App\Models\StatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StatusHistory
 */
class StatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => 'status',
            'shipment_id' => $this->shipment_id,
            'from' => $this->from_status?->value,
            'from_label' => $this->from_status?->label(),
            'to' => $this->to_status->value,
            'to_label' => $this->to_status->label(),
            'note' => $this->note,
            'source' => $this->source->value,
            'actor' => $this->actor_label,
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
