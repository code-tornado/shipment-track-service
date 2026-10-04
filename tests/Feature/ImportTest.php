<?php

namespace Tests\Feature;

use App\Enums\ShipmentStatus;
use App\Import\DeliveryPlanImporter;
use App\Models\CargoItem;
use App\Models\Container;
use App\Models\Customer;
use App\Models\ImportRun;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_delivery_plan_from_the_task_is_imported(): void
    {
        $user = $this->actingAsCompanyUser();

        $run = app(DeliveryPlanImporter::class)->import($this->seedFile(), $user);

        $this->assertSame(ImportRun::STATUS_COMPLETED, $run->status);
        $this->assertSame(178, $run->rows_total);
        $this->assertSame(178, $run->rows_imported);
        $this->assertSame(0, $run->rows_skipped);
        $this->assertSame(178, $run->cargo_items_created);
        $this->assertSame(66, $run->containers_created);
        $this->assertGreaterThan(30, $run->shipments_created);
        $this->assertSame(0, $run->issues()->where('level', 'error')->count());

        // "In Stock" / "In stock" (19 rows) and PO "Stock" (8 more rows) become in_storage with the stock flag.
        $this->assertSame(27, CargoItem::where('is_stock', true)->count());
        $this->assertSame(19, $run->issues()->where('column', 'status')->where('message', 'like', '%stock flag%')->count());
        $this->assertSame(0, Shipment::whereNotIn('status', ShipmentStatus::values())->count());

        // "GARWARE TECHNICAL FIBRES AS" and "Garware Technical Fibres AS" are one customer.
        $this->assertSame(7, Customer::count());

        // A net pen and its dead fish collector share a tag; both are kept.
        $this->assertSame(2, CargoItem::where('tag_no', 'GNP-2606018')->count());
        $this->assertTrue(CargoItem::where('tag_no', 'GLS-2511017')->exists(), '"GLS - 2511017" is normalised to GLS-2511017');

        // Air freight rows are imported under their AWB number.
        $this->assertSame(2, Container::whereIn('container_no', ['501-20079076', '501-20079065'])->count());
        $this->assertSame('air', Container::where('container_no', '501-20079076')->first()->shipment->shipping_method->value);

        // Mixed containers: delivered rows become delivered items on an in-storage shipment.
        $mixed = Container::where('container_no', 'MEDU4711735')->first();
        $this->assertSame(ShipmentStatus::InStorage, $mixed->shipment->status);
        $this->assertGreaterThan(0, $mixed->cargoItems()->whereNotNull('delivered_at')->count());
        $this->assertGreaterThan(0, $mixed->cargoItems()->whereNull('delivered_at')->count());

        // Rows without a tag are imported with a warning that names the row.
        $this->assertGreaterThan(50, $run->issues()->where('column', 'tag')->count());
        $this->assertDatabaseHas('import_issues', ['import_run_id' => $run->id, 'level' => 'warning', 'column' => 'material_code']);

        // Importing the same file again creates nothing and explains why.
        $again = app(DeliveryPlanImporter::class)->import($this->seedFile(), $user);
        $this->assertSame(0, $again->cargo_items_created);
        $this->assertSame(178, $again->rows_skipped);
        $this->assertSame(178, CargoItem::count());
        $this->assertStringContainsString('imported before', $again->issues()->where('column', 'package')->first()->message);
    }

    public function test_rows_that_cannot_be_imported_are_reported_and_the_rest_is_imported(): void
    {
        $user = $this->actingAsCompanyUser();

        $file = $this->spreadsheet([
            // valid
            ['Fisk AS West', 'RW26001', 862600578, 462600297, 25403000658, 'Net Cage', 'NP-164mCx1.3', 1, 'PC', 2730, 2830, 'WENA193722', 2606018, 'OK', 'Sea', 'BERGEN', 'HLBU8324720', '45UT', 'TRANSSEA AS', 'CIF', '08.08.2026', '29.09.2026', '', '', 'In Transit', '', '', '', '', '', '', ''],
            // valid, same container, "In stock" casing, no tag
            ['Fisk AS West', 'Stock', 862600578, 462600297, 25403000660, 'DFC', 'DFColle-1.6m', 1, 'PC', 32, 35, 'WENA193724', ' - ', '', 'Sea', 'BERGEN', 'HLBU8324720', '45UT', 'TRANSSEA AS', 'CIF', '08.08.2026', '29.09.2026', '', '', 'In stock', '', '', '', '', '', '', ''],
            // bad container number
            ['Fisk AS Mid', 'RM26001', 862600590, 462600223, 45503000619, 'Lice Shield', 'RW LICESHIELD', 1, 'PC', 33, 35, 'WENA193725', 2606020, '', 'Sea', 'ORKANGER', 'HLBU8324721', '45UT', 'TRANSSEA AS', 'CIF', '08.08.2026', '29.09.2026', '', '', 'In Transit', '', '', '', '', '', '', ''],
            // ETD after ETA
            ['Fisk AS Mid', 'RM26001', 862600590, 462600223, 45503000619, 'Lice Shield', 'RW LICESHIELD', 1, 'PC', 33, 35, 'WENA193726', 2606021, '', 'Sea', 'ORKANGER', 'HAMU4766088', '45UT', 'TRANSSEA AS', 'CIF', '10.10.2026', '29.09.2026', '', '', 'In Transit', '', '', '', '', '', '', ''],
            // unknown status
            ['Fisk AS Mid', 'RM26001', 862600590, 462600223, 45503000619, 'Lice Shield', 'RW LICESHIELD', 1, 'PC', 33, 35, 'WENA193727', 2606022, '', 'Sea', 'ORKANGER', 'HAMU4766088', '45UT', 'TRANSSEA AS', 'CIF', '08.08.2026', '29.09.2026', '', '', 'Lost', '', '', '', '', '', '', ''],
            // missing invoice and customer
            ['', '', '', 462600223, 45503000619, 'Lice Shield', 'RW LICESHIELD', 1, 'PC', 33, 35, 'WENA193728', 2606023, '', 'Sea', 'ORKANGER', 'HAMU4766088', '45UT', 'TRANSSEA AS', 'CIF', '08.08.2026', '29.09.2026', '', '', 'In Transit', '', '', '', '', '', '', ''],
            // duplicate package number of row 3
            ['Fisk AS West', 'RW26001', 862600578, 462600297, 25403000658, 'Net Cage', 'NP-164mCx1.3', 1, 'PC', 2730, 2830, 'WENA193722', 2606018, 'OK', 'Sea', 'BERGEN', 'HLBU8324720', '45UT', 'TRANSSEA AS', 'CIF', '08.08.2026', '29.09.2026', '', '', 'In Transit', '', '', '', '', '', '', ''],
        ]);

        $response = $this->post('/api/v1/imports', ['file' => $file])
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.rows_total', 7)
            ->assertJsonPath('data.rows_imported', 2)
            ->assertJsonPath('data.rows_skipped', 5)
            ->assertJsonPath('data.shipments_created', 1)
            ->assertJsonPath('data.containers_created', 1)
            ->assertJsonPath('data.cargo_items_created', 2);

        $errors = collect($response->json('data.issues'))->where('level', 'error')->groupBy('row_number');
        $this->assertSame([5, 6, 7, 8, 9], $errors->keys()->sort()->values()->all());
        $this->assertStringContainsString('not a valid ISO 6346', $errors[5][0]['message']);
        $this->assertStringContainsString('before ETD', $errors[6][0]['message']);
        $this->assertStringContainsString('Unknown status "Lost"', $errors[7][0]['message']);
        $this->assertStringContainsString('already appears on row 3', $errors[9][0]['message']);
        $this->assertSame(['customer', 'invoice'], $errors[8]->pluck('column')->sort()->values()->all());

        $warnings = collect($response->json('data.issues'))->where('level', 'warning');
        $this->assertTrue($warnings->contains(fn ($w) => $w['row_number'] === 4 && $w['column'] === 'tag'));
        $this->assertTrue($warnings->contains(fn ($w) => $w['row_number'] === 4 && str_contains($w['message'], 'stock flag')));

        $this->assertSame(1, CargoItem::where('is_stock', true)->count());
        $this->getJson('/api/v1/imports')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/imports/'.$response->json('data.id'))->assertOk()->assertJsonCount(9, 'data.issues');
        $this->assertSame($user->id, ImportRun::first()->user_id);
    }

    public function test_a_file_without_the_expected_columns_fails_cleanly(): void
    {
        $this->actingAsCompanyUser();

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([['Foo', 'Bar'], [1, 2]]);
        $path = tempnam(sys_get_temp_dir(), 'plan').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $this->post('/api/v1/imports', ['file' => new UploadedFile($path, 'plan.xlsx', null, null, true)])
            ->assertUnprocessable()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error', 'No header row found (a row with a "Customer Name" column is required).');

        $this->post('/api/v1/imports', ['file' => UploadedFile::fake()->create('plan.txt', 10, 'text/plain')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    /**
     * Build an .xlsx with the delivery plan's header row (same column order as the real sheet)
     * and the given data rows. Row 1 is a group header, row 2 the column header, data from row 3.
     *
     * @param  list<list<mixed>>  $rows
     */
    private function spreadsheet(array $rows): UploadedFile
    {
        $header = [
            'Customer Name', 'Customer PO', 'Invoice NO', 'Sales Order No', 'Material Code', 'Net type', 'Material Description',
            'Qty', 'Unit', 'Net w kgs', 'Gross w kgs', 'Batch No.', 'TAGNO - GNP:', 'Certificate sent to cust', 'Shipping method',
            'Destination Port', 'Container/ AWB No', 'Cont type', 'Shipping Line', 'Incoterm', 'ETD Mumbai', 'Planned ETA',
            'Arrival date/Actual ETA Port', 'Customer approved del date', 'Current Status', 'Customs cleared', 'Pickup date',
            'Storage', 'Storage date', 'Storage facility', 'Cust Delivery date', 'Comments',
        ];

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([['', 'Shipment'], $header, ...$rows]);

        $path = tempnam(sys_get_temp_dir(), 'plan').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'plan.xlsx', null, null, true);
    }
}
