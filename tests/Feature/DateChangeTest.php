<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\CargoItem;
use App\Models\Container;
use App\Models\Shipment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_shipment_dates_writes_an_audit_trail_and_shows_the_delay(): void
    {
        CarbonImmutable::setTestNow('2026-10-04');
        $user = $this->actingAsCompanyUser();
        $shipment = Shipment::factory()->for($user->company)->create(['status' => 'in_transit', 'etd' => '2026-08-08', 'eta' => '2026-09-17']);

        $this->patchJson("/api/v1/shipments/{$shipment->id}/dates", ['eta' => '2026-09-29', 'reason' => 'Vessel rerouted via Rotterdam'])
            ->assertOk()
            ->assertJsonPath('data.eta', '2026-09-29')
            ->assertJsonPath('data.days_delayed', 5) // today (Oct 4) is 5 days past the new ETA
            ->assertJsonPath('data.date_changes.0.field', 'eta')
            ->assertJsonPath('data.date_changes.0.old', '2026-09-17')
            ->assertJsonPath('data.date_changes.0.new', '2026-09-29')
            ->assertJsonPath('data.date_changes.0.days_shifted', 12)
            ->assertJsonPath('data.date_changes.0.reason', 'Vessel rerouted via Rotterdam')
            ->assertJsonPath('data.date_changes.0.actor', $user->name)
            ->assertJsonPath('data.date_changes.0.source', 'user');

        // Unchanged values do not produce audit rows.
        $this->patchJson("/api/v1/shipments/{$shipment->id}/dates", ['eta' => '2026-09-29', 'etd' => '2026-08-08', 'reason' => 'no-op'])
            ->assertOk()->assertJsonCount(1, 'data.date_changes');

        $this->assertDatabaseHas('date_changes', ['field' => 'eta', 'old_value' => '2026-09-17 00:00:00', 'new_value' => '2026-09-29 00:00:00', 'actor_id' => $user->id]);
    }

    public function test_a_reason_is_required_and_dates_must_stay_consistent(): void
    {
        $user = $this->actingAsCompanyUser();
        $shipment = Shipment::factory()->for($user->company)->create(['etd' => '2026-08-08', 'eta' => '2026-09-17']);

        $this->patchJson("/api/v1/shipments/{$shipment->id}/dates", ['eta' => '2026-09-29'])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->patchJson("/api/v1/shipments/{$shipment->id}/dates", ['reason' => 'nothing to change'])
            ->assertUnprocessable();

        $this->patchJson("/api/v1/shipments/{$shipment->id}/dates", ['eta' => '2026-08-01', 'reason' => 'typo'])
            ->assertUnprocessable()->assertJsonPath('errors.eta.0', 'ETA must be on or after ETD.');

        $this->patchJson("/api/v1/shipments/{$shipment->id}/dates", ['ata' => '2026-07-01', 'reason' => 'typo'])
            ->assertUnprocessable()->assertJsonPath('errors.ata.0', 'Actual arrival must be on or after ETD.');

        $this->assertSame('2026-09-17', $shipment->fresh()->eta->toDateString());
        $this->assertDatabaseCount('date_changes', 0);
    }

    public function test_customer_delivery_dates_are_audited_per_cargo_item_and_delay_is_computed(): void
    {
        CarbonImmutable::setTestNow('2026-10-04');
        $user = $this->actingAsCompanyUser();
        $shipment = Shipment::factory()->for($user->company)->status(ShipmentStatus::InStorage)->create();
        $container = Container::factory()->for($shipment)->create();
        $item = CargoItem::factory()->for($container)->create(['customer_delivery_date' => null]);
        $other = CargoItem::factory()->for($container)->create(['customer_delivery_date' => '2026-09-01', 'delivered_at' => '2026-09-01']);

        $this->patchJson("/api/v1/cargo-items/{$item->id}/dates", ['customer_delivery_date' => '2026-09-20', 'reason' => 'Customer approved date'])
            ->assertOk()
            ->assertJsonPath('data.customer_delivery_date', '2026-09-20')
            ->assertJsonPath('data.days_delayed', 14)
            ->assertJsonPath('data.status', 'in_storage')
            ->assertJsonPath('data.date_changes.0.field', 'customer_delivery_date')
            ->assertJsonPath('data.date_changes.0.old', null)
            ->assertJsonPath('data.date_changes.0.new', '2026-09-20');

        $this->patchJson("/api/v1/cargo-items/{$item->id}/dates", ['delivered_at' => '2026-09-25', 'reason' => 'Driven out to the site'])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.days_delayed', 5);

        // Shipment-level customer delivery date applies to items not delivered yet only.
        $third = CargoItem::factory()->for($container)->create(['customer_delivery_date' => '2026-10-10']);
        $this->patchJson("/api/v1/shipments/{$shipment->id}/dates", ['customer_delivery_date' => '2026-10-20', 'reason' => 'Site not ready'])
            ->assertOk();

        $this->assertSame('2026-10-20', $third->fresh()->customer_delivery_date->toDateString());
        $this->assertSame('2026-09-20', $item->fresh()->customer_delivery_date->toDateString());
        $this->assertSame('2026-09-01', $other->fresh()->customer_delivery_date->toDateString());

        $history = $this->getJson("/api/v1/shipments/{$shipment->id}/history")->assertOk()->json('data');
        $this->assertSame('cargo_item', $history[0]['subject_type']);
        $this->assertSame('Site not ready', $history[0]['reason']);
    }
}
