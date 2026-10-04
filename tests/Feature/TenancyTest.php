<?php

namespace Tests\Feature;

use App\Models\CargoItem;
use App\Models\Company;
use App\Models\Container;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every request belongs to a company; data never leaks between companies.
 */
class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_company_only_sees_its_own_shipments(): void
    {
        $theirs = Company::factory()->create();
        $theirShipment = Shipment::factory()->for($theirs)->create(['reference' => 'SHP-2026-0001']);
        $theirContainer = Container::factory()->for($theirShipment)->create(['container_no' => 'HLBU8324720']);
        $theirItem = CargoItem::factory()->for($theirContainer)->create(['proforma_invoice_no' => '862600578', 'tag_no' => 'GNP-2606018']);

        $this->actingAsCompanyUser();
        $mine = Shipment::factory()->for(auth()->user()->company)->create(['reference' => 'SHP-2026-0001']);

        $this->getJson('/api/v1/shipments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);

        $this->getJson("/api/v1/shipments/{$theirShipment->id}")->assertNotFound();
        $this->getJson("/api/v1/shipments/{$theirShipment->id}/history")->assertNotFound();
        $this->patchJson("/api/v1/containers/{$theirContainer->id}", ['seal_no' => 'X'])->assertNotFound();
        $this->patchJson("/api/v1/cargo-items/{$theirItem->id}/dates", ['delivered_at' => '2026-01-01', 'reason' => 'nope'])->assertNotFound();
        $this->postJson("/api/v1/shipments/{$theirShipment->id}/status", ['status' => 'arrived'])->assertNotFound();

        $this->getJson('/api/v1/lookup?q=HLBU8324720')->assertOk()->assertJsonCount(0, 'containers');
        $this->getJson('/api/v1/lookup?q=2606018')->assertOk()->assertJsonCount(0, 'cargo_items');
        $this->getJson('/api/v1/reports/status-overview')->assertOk()->assertJsonPath('highlights.shipments_total', 1);
    }

    public function test_created_records_are_stamped_with_the_callers_company(): void
    {
        $user = $this->actingAsCompanyUser();

        $id = $this->postJson('/api/v1/shipments', [
            'shipping_method' => 'sea',
            'destination_port' => 'BERGEN',
            'etd' => '2026-08-08',
            'eta' => '2026-09-29',
        ])->assertCreated()->json('data.id');

        $this->assertSame($user->company_id, Shipment::withoutGlobalScopes()->find($id)->company_id);
    }
}
