<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ShipmentResource extends ShipmentSummaryResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'containers' => ContainerResource::collection($this->containers),
            'status_history' => StatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'date_changes' => DateChangeResource::collection($this->whenLoaded('dateChanges')),
        ];
    }
}
