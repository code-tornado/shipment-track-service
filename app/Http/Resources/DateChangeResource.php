<?php

namespace App\Http\Resources;

use App\Models\CargoItem;
use App\Models\DateChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DateChange
 */
class DateChangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subjectLabel = null;
        if ($this->relationLoaded('subject') && $this->subject instanceof CargoItem) {
            $subjectLabel = $this->subject->tag_no ?? $this->subject->package_no ?? ('item #'.$this->subject->id);
        }

        return [
            'id' => $this->id,
            'type' => 'date',
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject_label' => $subjectLabel,
            'field' => $this->field->value,
            'field_label' => $this->field->label(),
            'old' => $this->old_value?->toDateString(),
            'new' => $this->new_value?->toDateString(),
            'days_shifted' => $this->daysShifted(),
            'reason' => $this->reason,
            'source' => $this->source->value,
            'actor' => $this->actor_label,
            'occurred_at' => $this->occurred_at->toIso8601String(),
        ];
    }
}
