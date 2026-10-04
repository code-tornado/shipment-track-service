<?php

namespace App\Models;

use App\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'url', 'secret', 'events', 'active'])]
#[Hidden(['secret'])]
class WebhookEndpoint extends Model
{
    use BelongsToCompany;

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'active' => 'boolean',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function listensTo(string $event): bool
    {
        $events = $this->events ?? ['*'];

        return in_array('*', $events, true) || in_array($event, $events, true);
    }
}
