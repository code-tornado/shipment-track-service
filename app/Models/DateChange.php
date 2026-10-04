<?php

namespace App\Models;

use App\Enums\ChangeSource;
use App\Enums\DateField;
use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Audit trail entry for a date owned by this service: which field, what it
 * was, what it became, who changed it, when and why.
 */
#[Fillable([
    'company_id', 'subject_type', 'subject_id', 'field', 'old_value', 'new_value',
    'reason', 'source', 'actor_id', 'actor_label', 'occurred_at',
])]
class DateChange extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'field' => DateField::class,
            'old_value' => 'immutable_date',
            'new_value' => 'immutable_date',
            'source' => ChangeSource::class,
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function daysShifted(): ?int
    {
        if ($this->old_value === null || $this->new_value === null) {
            return null;
        }

        return (int) $this->old_value->diffInDays($this->new_value, false);
    }
}
