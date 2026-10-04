<?php

namespace Tests\Feature;

use App\Models\CargoItem;
use App\Models\Container;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Shipment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsAndLookupTest extends TestCase
{
    use RefreshDatabase;

    private Shipment $transit;

    private Shipment $storage;

    private Shipment $late;

    private CargoItem $dueSoon;

    private CargoItem $overdue;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-04');

        $company = $this->actingAsCompanyUser()->company;
        $fisk = Customer::factory()->for($company)->create(['name' => 'Fisk AS West']);
        $finnmark = Customer::factory()->for($company)->create(['name' => 'Finnmark AS']);
        $netPen = Product::factory()->for($company)->create(['material_code' => '25403000658', 'description' => 'NP-164mCirx1.3+18+15/KNXWht/360p-16.5HM']);
        $lice = Product::factory()->for($company)->create(['material_code' => '45503000619', 'description' => 'RW LICESHIELD RECY X-12 175X8M 2001', 'net_type' => 'lice_shield']);

        // Still at sea, ETA in the window.
        $this->transit = Shipment::factory()->for($company)->create(['status' => 'in_transit', 'etd' => '2026-08-20', 'eta' => '2026-10-10']);
        Container::factory()->for($this->transit)->create(['container_no' => 'HLBU8073625']);

        // In storage, arrived 12 days late; one item due soon, one overdue.
        $this->storage = Shipment::factory()->for($company)->create(['status' => 'in_storage', 'etd' => '2026-08-08', 'eta' => '2026-09-17', 'ata' => '2026-09-29']);
        $container = Container::factory()->for($this->storage)->create(['container_no' => 'MEDU4711735']);
        $this->dueSoon = CargoItem::factory()->for($container)->create(['customer_id' => $fisk->id, 'product_id' => $netPen->id, 'proforma_invoice_no' => '862600578', 'tag_no' => 'GNP-2606018', 'customer_delivery_date' => '2026-10-08']);
        $this->overdue = CargoItem::factory()->for($container)->create(['customer_id' => $finnmark->id, 'product_id' => $lice->id, 'proforma_invoice_no' => '862600579', 'tag_no' => 'GNP-2606019', 'customer_delivery_date' => '2026-09-30']);
        CargoItem::factory()->for($container)->create(['customer_id' => $fisk->id, 'product_id' => $netPen->id, 'proforma_invoice_no' => '862600578', 'customer_delivery_date' => '2026-09-20', 'delivered_at' => '2026-09-20']);

        // Overdue at sea: ETA passed, not arrived.
        $this->late = Shipment::factory()->for($company)->create(['status' => 'in_transit', 'etd' => '2026-06-27', 'eta' => '2026-08-22']);
    }

    public function test_upcoming_deliveries_for_a_date_range(): void
    {
        $this->getJson('/api/v1/reports/upcoming-deliveries?from=2026-10-04&to=2026-10-14')
            ->assertOk()
            ->assertJsonPath('totals.deliveries', 1)
            ->assertJsonPath('deliveries.0.id', $this->dueSoon->id)
            ->assertJsonPath('deliveries.0.customer.name', 'Fisk AS West')
            ->assertJsonPath('deliveries.0.shipment.reference', $this->storage->reference)
            ->assertJsonPath('totals.arrivals', 1)
            ->assertJsonPath('arrivals.0.id', $this->transit->id);

        // Default window is the next 14 days; the overdue item is not "upcoming".
        $this->getJson('/api/v1/reports/upcoming-deliveries')->assertOk()->assertJsonPath('totals.deliveries', 1);
        $this->getJson('/api/v1/reports/upcoming-deliveries?from=2026-09-01&to=2026-09-30')->assertOk()
            ->assertJsonPath('totals.deliveries', 1)->assertJsonPath('deliveries.0.id', $this->overdue->id);
        $this->getJson('/api/v1/reports/upcoming-deliveries?from=2026-10-10&to=2026-10-01')->assertUnprocessable();
    }

    public function test_status_overview_counts_shipments_containers_and_items(): void
    {
        $this->getJson('/api/v1/reports/status-overview')
            ->assertOk()
            ->assertJsonPath('by_status.shipments.in_transit', 2)
            ->assertJsonPath('by_status.shipments.in_storage', 1)
            ->assertJsonPath('by_status.shipments.delivered', 0)
            ->assertJsonPath('by_status.containers.in_storage', 1)
            ->assertJsonPath('by_status.cargo_items.in_storage', 2)
            ->assertJsonPath('by_status.cargo_items.delivered', 1)
            ->assertJsonPath('by_customer.0.customer', 'Finnmark AS')
            ->assertJsonPath('by_customer.1.customer', 'Fisk AS West')
            ->assertJsonPath('by_customer.1.delivered', 1)
            ->assertJsonPath('highlights.shipments_total', 3)
            ->assertJsonPath('highlights.shipments_delayed', 2)
            ->assertJsonPath('highlights.arriving_next_7_days', 1)
            ->assertJsonPath('highlights.deliveries_late', 1)
            ->assertJsonPath('highlights.deliveries_next_7_days', 1);
    }

    public function test_delayed_report_lists_late_shipments_and_late_deliveries(): void
    {
        $response = $this->getJson('/api/v1/reports/delayed')->assertOk()
            ->assertJsonPath('totals.shipments', 2)
            ->assertJsonPath('totals.cargo_items', 1)
            ->assertJsonPath('cargo_items.0.id', $this->overdue->id)
            ->assertJsonPath('cargo_items.0.days_delayed', 4);

        $shipments = collect($response->json('shipments'))->keyBy('id');
        $this->assertSame(43, $shipments[$this->late->id]['days_delayed']);   // Aug 22 → Oct 4
        $this->assertSame(12, $shipments[$this->storage->id]['days_delayed']); // Sep 17 → Sep 29
        $this->assertSame($this->late->id, $response->json('shipments.0.id'));

        $this->getJson('/api/v1/shipments?delayed=1')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_lookup_by_container_invoice_tag_product_and_customer(): void
    {
        $this->getJson('/api/v1/lookup?q=MEDU4711735&type=container')->assertOk()
            ->assertJsonCount(1, 'containers')
            ->assertJsonPath('containers.0.shipment.reference', $this->storage->reference)
            ->assertJsonCount(1, 'shipments');

        $this->getJson('/api/v1/lookup?q=862600578&type=invoice')->assertOk()->assertJsonCount(2, 'cargo_items');

        $this->getJson('/api/v1/lookup?q=2606019&type=tag')->assertOk()
            ->assertJsonCount(1, 'cargo_items')->assertJsonPath('cargo_items.0.id', $this->overdue->id);

        $this->getJson('/api/v1/lookup?q=liceshield&type=product')->assertOk()->assertJsonCount(1, 'cargo_items');
        $this->getJson('/api/v1/lookup?q=25403000658&type=product')->assertOk()->assertJsonCount(2, 'cargo_items');

        $this->getJson('/api/v1/lookup?q=finnmark&type=customer')->assertOk()->assertJsonCount(1, 'cargo_items');

        // Untyped lookup searches everything at once.
        $this->getJson('/api/v1/lookup?q=HLBU8073625')->assertOk()->assertJsonCount(1, 'containers')->assertJsonCount(0, 'cargo_items');
        $this->getJson('/api/v1/lookup?q=x')->assertUnprocessable();
        $this->getJson('/api/v1/lookup?q=nothing-here')->assertOk()->assertJsonCount(0, 'shipments');
    }
}
