<?php

namespace App\Services;

use App\Enums\ChangeSource;
use App\Models\User;

/**
 * Who is making a change: a user, the Excel import, an external tracking
 * provider, or the system itself. Written to every history/audit row.
 */
final class Actor
{
    private function __construct(
        public readonly ?int $userId,
        public readonly string $label,
        public readonly ChangeSource $source,
    ) {}

    public static function user(User $user): self
    {
        return new self($user->id, $user->name, ChangeSource::User);
    }

    public static function import(?User $user = null): self
    {
        return new self($user?->id, 'Excel import'.($user ? ' ('.$user->name.')' : ''), ChangeSource::Import);
    }

    public static function provider(string $name): self
    {
        return new self(null, 'provider:'.$name, ChangeSource::Provider);
    }

    public static function system(string $label = 'system'): self
    {
        return new self(null, $label, ChangeSource::System);
    }
}
