<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Models\CargoItem;
use App\Models\Container;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentCrudTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'shipping_method' => 'sea',
            'shipping_line' => 'TRANSSEA AS',
            'destination_port' => 'BERGEN',
            'incoterm' => 'CIF',
            'etd' => '2026-08-08',
            'eta' => '2026-09-29',
            'status' => 'in_transit',
            'containers' => [[
                'container_no' => 'HLBU8324720',
                'seal_no' => 'SL123',
                'container_type' => '45UT',
                'cargo_items' => [[
                    'proforma_invoice_no' => '862600578',
                    'exporter_ref' => '462600297',
                    'customer_po' => 'RW26001',
                    'tag_no' => '2606018',
                    'package_no' => 'WENA193722',
                    'quantity' => 1,
                    'net_weight_kg' => 2730,
                    'gross_weight_kg' => 2830,
                    'customer_name' => 'Fisk AS West',
                    'product' => ['material_code' => '25403000658', 'description' => 'NP-164mCx1.3+18+16m/KNXPlus/540p-29HM/O1'],
                    'customer_delivery_date' => '2026-11-01',
                ], [
                    'proforma_invoice_no' => '862600578',
                    'tag_no' => 'GNP-2606018',
                    'package_no' => 'WENA193724',
                    'customer_name' => 'fisk as west',
                    'product' => ['material_code' => '25403000660', 'description' => 'DFColle-1.6mDia/N6132-16.5/V4PlatN/DblRi', 'net_type' => 'dead_fish_collector'],
                ]],
            ]],
        ], $overrides);
    }

    public function test_a_shipment_with_containers_and_cargo_is_created(): void
    {
        $this->actingAsCompanyUser();

        $response = $this->postJson('/api/v1/shipments', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.reference', 'SHP-2026-0001')
            ->assertJsonPath('data.status', 'in_transit')
            ->assertJsonPath('data.containers_count', 1)
            ->assertJsonPath('data.cargo_items_count', 2)
            ->assertJsonPath('data.customers', ['Fisk AS West'])
            ->assertJsonPath('data.containers.0.container_no', 'HLBU8324720')
            ->assertJsonPath('data.containers.0.cargo_items.0.tag_no', 'GNP-2606018')
            ->assertJsonPath('data.containers.0.cargo_items.0.product.net_type', 'net_pen')
            ->assertJsonPath('data.containers.0.cargo_items.1.product.net_type', 'dead_fish_collector')
            ->assertJsonPath('data.containers.0.cargo_items.0.status', 'in_transit')
            ->assertJsonCount(1, 'data.status_history');

        // Both items point at the same customer although the names differ in case.
        $items = CargoItem::all();
        $this->assertCount(1, $items->pluck('customer_id')->unique());

        $this->getJson('/api/v1/shipments/'.$response->json('data.id'))->assertOk()
            ->assertJsonPath('data.status_history.0.to', 'in_transit');
    }

    public function test_second_shipment_gets_the_next_reference(): void
    {
        $this->actingAsCompanyUser();

        $this->postJson('/api/v1/shipments', $this->payload())->assertCreated();
        $this->postJson('/api/v1/shipments', $this->payload(['containers' => [['container_no' => 'MEDU4711735', 'cargo_items' => [['package_no' => 'X1'], ['package_no' => 'X2']]]]]))
            ->assertCreated()
            ->assertJsonPath('data.reference', 'SHP-2026-0002');
    }

    public function test_invalid_container_number_is_rejected(): void
    {
        $this->actingAsCompanyUser();

        $this->postJson('/api/v1/shipments', $this->payload(['containers' => [['container_no' => 'HLBU8324721']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['containers.0.container_no']);

        $this->postJson('/api/v1/shipments', $this->payload(['containers' => [['container_no' => 'NOT-A-NUMBER']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['containers.0.container_no']);
    }

    public function test_air_freight_uses_air_waybill_numbers(): void
    {
        $this->actingAsCompanyUser();

        $this->postJson('/api/v1/shipments', $this->payload(['shipping_method' => 'air', 'containers' => [['container_no' => 'HLBU8324720']]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['containers.0.container_no']);

        $this->postJson('/api/v1/shipments', $this->payload(['shipping_method' => 'air', 'containers' => [['container_no' => '50120079076']]]))
            ->assertCreated()
            ->assertJsonPath('data.containers.0.container_no', '501-20079076');
    }

    public function test_etd_must_not_be_after_eta(): void
    {
        $this->actingAsCompanyUser();

        $this->postJson('/api/v1/shipments', $this->payload(['etd' => '2026-10-01', 'eta' => '2026-09-29']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['eta']);

        $this->postJson('/api/v1/shipments', $this->payload(['ata' => '2026-08-01']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ata']);
    }

    public function test_containers_and_cargo_items_can_be_added_and_changed_later(): void
    {
        $this->actingAsCompanyUser();
        $shipmentId = $this->postJson('/api/v1/shipments', $this->payload(['containers' => []]))->json('data.id');

        $containerId = $this->postJson("/api/v1/shipments/{$shipmentId}/containers", [
            'container_no' => 'hamu4766088',
            'container_type' => '45GP',
        ])->assertCreated()->assertJsonPath('data.container_no', 'HAMU4766088')->json('data.id');

        // Same container twice on one shipment is rejected.
        $this->postJson("/api/v1/shipments/{$shipmentId}/containers", ['container_no' => 'HAMU4766088'])
            ->assertUnprocessable()->assertJsonValidationErrors(['container_no']);

        $itemId = $this->postJson("/api/v1/containers/{$containerId}/cargo-items", [
            'proforma_invoice_no' => '862600578',
            'tag_no' => 'GLS - 2511017',
            'package_no' => 'WENA000001',
            'customer_name' => 'Finnmark AS',
            'product' => ['description' => 'RW LICESHIELD RECY X-12 175X8M 2001', 'net_type' => 'lice_shield'],
        ])->assertCreated()->assertJsonPath('data.tag_no', 'GLS-2511017')->json('data.id');

        $this->patchJson("/api/v1/cargo-items/{$itemId}", ['seal_no' => 'ignored', 'customer_po' => 'RN26007', 'quantity' => 3])
            ->assertOk()->assertJsonPath('data.customer_po', 'RN26007')->assertJsonPath('data.quantity', 3);

        $this->patchJson("/api/v1/containers/{$containerId}", ['seal_no' => 'SEAL-9', 'customs_cleared' => true])
            ->assertOk()->assertJsonPath('data.seal_no', 'SEAL-9')->assertJsonPath('data.customs_cleared', true);

        $this->patchJson("/api/v1/shipments/{$shipmentId}", ['shipping_line' => 'SKARSK', 'notes' => 'Rotterdam to Bergen by boat'])
            ->assertOk()->assertJsonPath('data.shipping_line', 'SKARSK');

        $this->deleteJson("/api/v1/cargo-items/{$itemId}")->assertNoContent();
        $this->deleteJson("/api/v1/containers/{$containerId}")->assertNoContent();
        $this->deleteJson("/api/v1/shipments/{$shipmentId}")->assertNoContent();
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_list_can_be_filtered(): void
    {
        $user = $this->actingAsCompanyUser();
        $company = $user->company;

        $transit = Shipment::factory()->for($company)->create(['status' => 'in_transit', 'destination_port' => 'BERGEN', 'etd' => '2026-08-08', 'eta' => '2026-09-29']);
        $storage = Shipment::factory()->for($company)->status(ShipmentStatus::InStorage)->create(['destination_port' => 'ORKANGER', 'etd' => '2026-02-05', 'eta' => '2026-04-01']);
        $container = Container::factory()->for($storage)->create(['container_no' => 'HLBU8073625']);
        CargoItem::factory()->for($container)->create(['proforma_invoice_no' => '862600380', 'tag_no' => 'GNP-2606099']);

        $this->getJson('/api/v1/shipments?status=in_transit')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $transit->id);
        $this->getJson('/api/v1/shipments?status=in_storage,delivered')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $storage->id);
        $this->getJson('/api/v1/shipments?destination_port=ork')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/shipments?container_no=HLBU8073625')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $storage->id);
        $this->getJson('/api/v1/shipments?proforma_invoice_no=862600380')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/shipments?tag_no=2606099')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/shipments?q=2606099')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/shipments?eta_from=2026-09-01')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $transit->id);
        $this->getJson('/api/v1/shipments?sort=eta')->assertOk()->assertJsonPath('data.0.id', $storage->id);
        $this->getJson('/api/v1/shipments?sort=-eta')->assertOk()->assertJsonPath('data.0.id', $transit->id);
    }
}
