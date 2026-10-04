<?php

namespace Tests\Unit;

use App\Enums\ShipmentStatus;
use App\Tracking\TrackingSyncService;
use PHPUnit\Framework\TestCase;

class ShipmentStatusTest extends TestCase
{
    public function test_flow_only_moves_forward(): void
    {
        $this->assertTrue(ShipmentStatus::Planned->canTransitionTo(ShipmentStatus::InTransit));
        $this->assertTrue(ShipmentStatus::InTransit->canTransitionTo(ShipmentStatus::Arrived));
        $this->assertTrue(ShipmentStatus::InTransit->canTransitionTo(ShipmentStatus::InStorage));
        $this->assertTrue(ShipmentStatus::Arrived->canTransitionTo(ShipmentStatus::InStorage));
        $this->assertTrue(ShipmentStatus::Arrived->canTransitionTo(ShipmentStatus::Delivered));
        $this->assertTrue(ShipmentStatus::InStorage->canTransitionTo(ShipmentStatus::Delivered));

        $this->assertFalse(ShipmentStatus::Planned->canTransitionTo(ShipmentStatus::Arrived));
        $this->assertFalse(ShipmentStatus::Arrived->canTransitionTo(ShipmentStatus::InTransit));
        $this->assertFalse(ShipmentStatus::Delivered->canTransitionTo(ShipmentStatus::InStorage));
        $this->assertSame([], ShipmentStatus::Delivered->allowedTransitions());
    }

    public function test_excel_labels_are_normalised(): void
    {
        $this->assertSame(ShipmentStatus::InTransit, ShipmentStatus::tryFromLabel('In Transit'));
        $this->assertSame(ShipmentStatus::InStorage, ShipmentStatus::tryFromLabel('In storage'));
        $this->assertSame(ShipmentStatus::InStorage, ShipmentStatus::tryFromLabel('In Stock'));
        $this->assertSame(ShipmentStatus::InStorage, ShipmentStatus::tryFromLabel('  in stock '));
        $this->assertSame(ShipmentStatus::Delivered, ShipmentStatus::tryFromLabel('DELIVERED'));
        $this->assertNull(ShipmentStatus::tryFromLabel('Lost at sea'));
        $this->assertNull(ShipmentStatus::tryFromLabel(null));
    }

    public function test_shortest_path_between_statuses(): void
    {
        $this->assertSame(
            [ShipmentStatus::InTransit, ShipmentStatus::Arrived],
            TrackingSyncService::pathTo(ShipmentStatus::Planned, ShipmentStatus::Arrived)
        );
        $this->assertSame(
            [ShipmentStatus::InStorage],
            TrackingSyncService::pathTo(ShipmentStatus::InTransit, ShipmentStatus::InStorage)
        );
        $this->assertSame([], TrackingSyncService::pathTo(ShipmentStatus::Delivered, ShipmentStatus::Arrived));
    }
}
