<?php

namespace App\Exceptions;

use App\Enums\ShipmentStatus;

class InvalidStatusTransition extends DomainException
{
    public function __construct(ShipmentStatus $from, ShipmentStatus $to)
    {
        $allowed = array_map(fn (ShipmentStatus $s) => $s->value, $from->allowedTransitions());

        parent::__construct(
            sprintf(
                'Cannot change status from %s to %s. Allowed: %s.',
                $from->value,
                $to->value,
                $allowed ? implode(', ', $allowed) : 'none (final status)'
            ),
            ['status' => ['Transition not allowed.']]
        );
    }
}
