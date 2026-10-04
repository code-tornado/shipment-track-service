<?php

namespace App\Models;

use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id', 'user_id', 'filename', 'status', 'rows_total', 'rows_imported', 'rows_skipped',
    'warnings_count', 'shipments_created', 'containers_created', 'cargo_items_created', 'error',
    'started_at', 'finished_at',
])]
class ImportRun extends Model
{
    use BelongsToCompany;

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ImportIssue::class)->orderBy('row_number')->orderBy('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
