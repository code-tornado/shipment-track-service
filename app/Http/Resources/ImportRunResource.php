<?php

namespace App\Http\Resources;

use App\Models\ImportRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ImportRun
 */
class ImportRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'status' => $this->status,
            'rows_total' => $this->rows_total,
            'rows_imported' => $this->rows_imported,
            'rows_skipped' => $this->rows_skipped,
            'warnings_count' => $this->warnings_count,
            'shipments_created' => $this->shipments_created,
            'containers_created' => $this->containers_created,
            'cargo_items_created' => $this->cargo_items_created,
            'error' => $this->error,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => $this->user?->name),
            'issues' => $this->whenLoaded('issues', fn () => $this->issues->map(fn ($issue) => [
                'id' => $issue->id,
                'row_number' => $issue->row_number,
                'level' => $issue->level,
                'column' => $issue->column,
                'message' => $issue->message,
                'raw' => $issue->raw,
            ])),
        ];
    }
}
