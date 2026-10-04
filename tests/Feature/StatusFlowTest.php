<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\CargoItem;
use App\Models\Container;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_moves_along_the_flow_and_keeps_history(): void
    {
        $user = $this->actingAsCompanyUser();
        $shipment = Shipment::factory()->for($user->company)->create(['status' => 'in_transit', 'etd' => '2026-08-08', 'eta' => '2026-09-17']);
        $container = Container::factory()->for($shipment)->create();
        $item = CargoItem::factory()->for($container)->create();

        $this->postJson("/api/v1/shipments/{$shipment->id}/status", ['status' => 'arrived', 'note' => 'Docked in Bergen', 'occurred_at' => '2026-09-29'])
            ->assertOk()
            ->assertJsonPath('data.status', 'arrived')
            ->assertJsonPath('data.ata', '2026-09-29')
            ->assertJsonPath('data.days_delayed', 12)
            ->assertJsonPath('data.allowed_transitions', ['in_storage', 'delivered']);

        $this->postJson("/api/v1/shipments/{$shipment->id}/status", ['status' => 'in_storage'])
            ->assertOk()->assertJsonPath('data.status', 'in_storage');

        $this->assertNotNull($container->fresh()->storage_date);

        $this->postJson("/api/v1/shipments/{$shipment->id}/status", ['status' => 'delivered', 'note' => 'Last net out'])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.delivered_items_count', 1)
            ->assertJsonPath('data.allowed_transitions', []);

        $this->assertNotNull($item->fresh()->delivered_at);

        $history = $this->getJson("/api/v1/shipments/{$shipment->id}/history")->assertOk()->json('data');
        $statusEntries = array_values(array_filter($history, fn ($e) => $e['type'] === 'status'));

        $this->assertSame(['delivered', 'in_storage', 'arrived'], array_column($statusEntries, 'to'));
        $this->assertSame(['in_storage', 'arrived', 'in_transit'], array_column($statusEntries, 'from'));
        $this->assertSame('Docked in Bergen', $statusEntries[2]['note']);
        $this->assertSame($user->name, $statusEntries[2]['actor']);
        $this->assertSame('user', $statusEntries[2]['source']);

        // Arrival and delivery dates set by the status change are audited too.
        $dateEntries = array_values(array_filter($history, fn ($e) => $e['type'] === 'date'));
        $this->assertSame(['delivered_at', 'ata'], array_column($dateEntries, 'field'));
    }

    public function test_transitions_outside_the_flow_are_rejected(): void
    {
        $user = $this->actingAsCompanyUser();
        $shipment = Shipment::factory()->for($user->company)->create(['status' => 'in_transit']);

        $this->postJson("/api/v1/shipments/{$shipment->id}/status", ['status' => 'delivered'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Transition not allowed.')
            ->assertJsonFragment(['message' => 'Cannot change status from in_transit to delivered. Allowed: arrived, in_storage.']);

        $this->postJson("/api/v1/shipments/{$shipment->id}/status", ['status' => 'planned'])->assertUnprocessable();
        $this->postJson("/api/v1/shipments/{$shipment->id}/status", ['status' => 'lost'])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame(ShipmentStatus::InTransit, $shipment->fresh()->status);
        $this->assertDatabaseCount('status_history', 0);
    }
}
