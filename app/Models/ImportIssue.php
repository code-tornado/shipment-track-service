<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['import_run_id', 'row_number', 'level', 'column', 'message', 'raw'])]
class ImportIssue extends Model
{
    public const UPDATED_AT = null;

    public const LEVEL_ERROR = 'error';

    public const LEVEL_WARNING = 'warning';

    protected function casts(): array
    {
        return [
            'raw' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }
}
