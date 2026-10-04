<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\CargoItem;
use App\Models\Container;
use App\Models\Shipment;
use App\Tracking\DeliveryUpdate;
use App\Tracking\FakeTrackingProvider;
use App\Tracking\TrackingProvider;
use App\Tracking\TrackingSyncService;
use App\Tracking\TrackingUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Status and dates can come from an external provider (container tracking,
 * Shipmondo) instead of manual entry; the fake stands in for a real adapter.
 */
class TrackingProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_updates_go_through_the_same_audit_trail_as_manual_changes(): void
    {
        CarbonImmutable::setTestNow('2026-10-04 10:00:00');
        $fake = new FakeTrackingProvider;
        $this->app->instance(TrackingProvider::class, $fake);

        $user = $this->actingAsCompanyUser();
        $shipment = Shipment::factory()->for($user->company)->create(['status' => 'in_transit', 'etd' => '2026-08-08', 'eta' => '2026-09-17']);
        $container = Container::factory()->for($shipment)->create(['container_no' => 'HLBU8324720']);
        $item = CargoItem::factory()->for($container)->create(['tag_no' => 'GNP-2606018', 'customer_delivery_date' => '2026-10-10']);

        $fake->willReportContainer('HLBU8324720', new TrackingUpdate(
            status: ShipmentStatus::Arrived,
            eta: CarbonImmutable::parse('2026-09-29'),
            ata: CarbonImmutable::parse('2026-09-29'),
            note: 'Discharged at Bergen',
        ));
        $fake->willReportDelivery('GNP-2606018', new DeliveryUpdate(deliveredAt: CarbonImmutable::parse('2026-10-03'), note: 'Signed by site manager'));

        $summary = app(TrackingSyncService::class)->syncShipment($shipment);

        $this->assertSame(['status_changes' => 1, 'date_changes' => 2, 'delivery_changes' => 1], $summary);
        $this->assertSame(['HLBU8324720'], $fake->queriedContainers);

        $shipment->refresh();
        $this->assertSame(ShipmentStatus::Arrived, $shipment->status);
        $this->assertSame('2026-09-29', $shipment->eta->toDateString());
        $this->assertSame('2026-09-29', $shipment->ata->toDateString());
        $this->assertSame('2026-10-03', $item->fresh()->delivered_at->toDateString());

        $history = $this->getJson("/api/v1/shipments/{$shipment->id}/history")->assertOk()->json('data');
        $this->assertCount(4, $history);
        foreach ($history as $entry) {
            $this->assertSame('provider', $entry['source']);
            $this->assertSame('provider:fake', $entry['actor']);
        }
        $statusEntry = collect($history)->firstWhere('type', 'status');
        $this->assertSame('Discharged at Bergen', $statusEntry['note']);
    }

    public function test_sync_command_walks_the_status_flow_and_skips_delivered_shipments(): void
    {
        $fake = new FakeTrackingProvider;
        $this->app->instance(TrackingProvider::class, $fake);

        $user = $this->actingAsCompanyUser();
        $planned = Shipment::factory()->for($user->company)->create(['status' => 'planned', 'reference' => 'SHP-2026-0001']);
        Container::factory()->for($planned)->create(['container_no' => 'MEDU4711735']);
        $done = Shipment::factory()->for($user->company)->status(ShipmentStatus::Delivered)->create(['reference' => 'SHP-2026-0002']);
        Container::factory()->for($done)->create(['container_no' => 'HAMU4766088']);

        $fake->willReportContainer('MEDU4711735', new TrackingUpdate(status: ShipmentStatus::InStorage));
        $fake->willReportContainer('HAMU4766088', new TrackingUpdate(status: ShipmentStatus::InStorage));

        $this->artisan('tracking:sync')->assertSuccessful();

        $this->assertSame(ShipmentStatus::InStorage, $planned->fresh()->status);
        $this->assertSame(['in_transit', 'in_storage'], $planned->statusHistory()->reorder('id')->pluck('to_status')->map->value->all());
        $this->assertSame(['MEDU4711735'], $fake->queriedContainers);
    }

    public function test_the_default_provider_is_manual_and_changes_nothing(): void
    {
        $user = $this->actingAsCompanyUser();
        $shipment = Shipment::factory()->for($user->company)->create(['status' => 'in_transit']);
        Container::factory()->for($shipment)->create();

        $this->assertSame('manual', app(TrackingProvider::class)->name());
        $this->assertSame(['status_changes' => 0, 'date_changes' => 0, 'delivery_changes' => 0], app(TrackingSyncService::class)->syncShipment($shipment));
    }
}
