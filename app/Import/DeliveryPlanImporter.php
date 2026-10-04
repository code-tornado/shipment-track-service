<?php

namespace App\Import;

use App\Enums\DateField;
use App\Enums\ShipmentStatus;
use App\Models\CargoItem;
use App\Models\Container;
use App\Models\ImportIssue;
use App\Models\ImportRun;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Actor;
use App\Services\DateChangeService;
use App\Services\ShipmentService;
use App\Services\ShipmentStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Imports the hand-kept "Delivery Plan" Excel sheet.
 *
 * One spreadsheet row is one cargo item (a net, a panel, a bundle of straps).
 * Rows are grouped into containers by container number and containers into
 * shipments by sailing (method, line, port, ETD, planned ETA). Rows that
 * cannot be imported are reported with the row number and the reason; rows
 * that needed normalisation are imported with a warning. Re-importing the
 * same file is safe: rows whose package number already exists are skipped.
 */
class DeliveryPlanImporter
{
    /** @var list<array<string, mixed>> */
    private array $issues = [];

    private int $shipmentsCreated = 0;

    private int $containersCreated = 0;

    private int $itemsCreated = 0;

    public function __construct(
        private readonly ShipmentService $shipments,
        private readonly ShipmentStatusService $status,
        private readonly DateChangeService $dates,
    ) {}

    public function import(string $path, ?User $user = null, ?string $filename = null): ImportRun
    {
        $this->issues = [];
        $this->shipmentsCreated = $this->containersCreated = $this->itemsCreated = 0;

        $run = ImportRun::create([
            'user_id' => $user?->id,
            'filename' => $filename ?? basename($path),
            'status' => ImportRun::STATUS_RUNNING,
            'started_at' => CarbonImmutable::now(),
        ]);

        try {
            $rows = $this->parseFile($path);
            $this->markAlreadyImported($rows);
            $this->importRows($rows, Actor::import($user));

            $imported = count(array_filter($rows, fn (ParsedRow $r) => $r->isValid()));

            $run->fill([
                'status' => ImportRun::STATUS_COMPLETED,
                'rows_total' => count($rows),
                'rows_imported' => $imported,
                'rows_skipped' => count($rows) - $imported,
                'shipments_created' => $this->shipmentsCreated,
                'containers_created' => $this->containersCreated,
                'cargo_items_created' => $this->itemsCreated,
            ]);
        } catch (ImportException $e) {
            $run->fill(['status' => ImportRun::STATUS_FAILED, 'error' => $e->getMessage()]);
        }

        $this->persistIssues($run);

        $run->fill([
            'warnings_count' => count(array_filter($this->issues, fn ($i) => $i['level'] === ImportIssue::LEVEL_WARNING)),
            'finished_at' => CarbonImmutable::now(),
        ])->save();

        return $run;
    }

    /**
     * @return list<ParsedRow>
     */
    private function parseFile(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getSheet(0);
            $cells = $sheet->toArray(null, false, false, false);
        } catch (Throwable $e) {
            throw new ImportException('The file could not be read as a spreadsheet: '.$e->getMessage());
        }

        $parser = new RowParser;
        $headerIndex = null;

        foreach ($cells as $index => $row) {
            if (RowParser::isHeaderRow($row)) {
                $headerIndex = $index;
                break;
            }
        }

        if ($headerIndex === null) {
            throw new ImportException('No header row found (a row with a "Customer Name" column is required).');
        }

        $missing = $parser->mapHeader($cells[$headerIndex]);
        if ($missing) {
            throw new ImportException('Missing required column(s): '.implode(', ', $missing).'.');
        }

        $rows = [];
        foreach (array_slice($cells, $headerIndex + 1, null, true) as $index => $row) {
            if (RowParser::isEmptyRow($row)) {
                continue;
            }
            $rows[] = $parser->parse($index + 1, $row);
        }

        $this->flagDuplicatePackages($rows);

        return $rows;
    }

    /**
     * @param  list<ParsedRow>  $rows
     */
    private function flagDuplicatePackages(array $rows): void
    {
        $seen = [];
        foreach ($rows as $row) {
            if ($row->packageNo === null) {
                continue;
            }
            if (isset($seen[$row->packageNo])) {
                $row->error('package', sprintf('Package number %s already appears on row %d.', $row->packageNo, $seen[$row->packageNo]));
            } else {
                $seen[$row->packageNo] = $row->rowNumber;
            }
        }
    }

    /**
     * Rows whose package number is already in the database were imported earlier.
     *
     * @param  list<ParsedRow>  $rows
     */
    private function markAlreadyImported(array $rows): void
    {
        $packageNos = array_values(array_unique(array_filter(array_map(fn (ParsedRow $r) => $r->packageNo, $rows))));
        if (! $packageNos) {
            return;
        }

        $existing = CargoItem::query()->whereIn('package_no', $packageNos)->pluck('package_no')->flip();

        foreach ($rows as $row) {
            if ($row->packageNo !== null && isset($existing[$row->packageNo]) && ! $row->hasErrors()) {
                $row->skippedBecause = sprintf('Package number %s was imported before; row skipped.', $row->packageNo);
                $row->warn('package', $row->skippedBecause);
            }
        }
    }

    /**
     * @param  list<ParsedRow>  $rows
     */
    private function importRows(array $rows, Actor $actor): void
    {
        $valid = array_filter($rows, fn (ParsedRow $r) => $r->isValid());

        // A container is as far along as its least advanced row; that status
        // becomes part of the grouping key so containers on the same sailing
        // but at different stages become separate shipments.
        $containerStatus = [];
        foreach ($valid as $row) {
            $current = $containerStatus[$row->containerNo] ?? null;
            if ($current === null || $row->status->order() < $current->order()) {
                $containerStatus[$row->containerNo] = $row->status;
            }
        }

        $groups = [];
        foreach ($valid as $row) {
            $groups[$row->shipmentKey($containerStatus[$row->containerNo])][] = $row;
        }

        uasort($groups, fn (array $a, array $b) => [$a[0]->etd, $a[0]->eta] <=> [$b[0]->etd, $b[0]->eta]);

        foreach ($groups as $group) {
            try {
                DB::transaction(fn () => $this->importGroup($group, $containerStatus, $actor));
            } catch (Throwable $e) {
                foreach ($group as $row) {
                    $row->error('row', 'Could not be saved: '.$e->getMessage());
                }
            }
        }

        foreach ($rows as $row) {
            foreach ($row->errors as $issue) {
                $this->issue($row, ImportIssue::LEVEL_ERROR, $issue['column'], $issue['message']);
            }
            foreach ($row->warnings as $issue) {
                $this->issue($row, ImportIssue::LEVEL_WARNING, $issue['column'], $issue['message']);
            }
        }
    }

    /**
     * @param  list<ParsedRow>  $group  rows of one shipment
     * @param  array<string, ShipmentStatus>  $containerStatus
     */
    private function importGroup(array $group, array $containerStatus, Actor $actor): void
    {
        $first = $group[0];
        $status = $containerStatus[$first->containerNo];
        $containerNos = array_values(array_unique(array_map(fn (ParsedRow $r) => $r->containerNo, $group)));

        $shipment = $this->findExistingShipment($first, $containerNos) ?? $this->createShipment($group, $status, $actor);

        foreach ($containerNos as $containerNo) {
            $rows = array_values(array_filter($group, fn (ParsedRow $r) => $r->containerNo === $containerNo));
            $container = $this->findOrCreateContainer($shipment, $containerNo, $rows);

            foreach ($rows as $row) {
                $this->createItem($container, $row, $status);
            }
        }
    }

    /**
     * A re-import must land rows on the shipment they belong to, even if its
     * dates have been corrected in the meantime: match by container first.
     *
     * @param  list<string>  $containerNos
     */
    private function findExistingShipment(ParsedRow $row, array $containerNos): ?Shipment
    {
        $container = Container::query()
            ->whereIn('container_no', $containerNos)
            ->whereHas('shipment', fn ($q) => $q->whereDate('etd', $row->etd->toDateString()))
            ->first();

        if ($container) {
            return $container->shipment;
        }

        return Shipment::query()
            ->where('shipping_method', $row->method)
            ->where('destination_port', $row->destinationPort)
            ->whereDate('etd', $row->etd->toDateString())
            ->whereDate('eta', $row->eta->toDateString())
            ->when($row->shippingLine, fn ($q, $line) => $q->where('shipping_line', $line), fn ($q) => $q->whereNull('shipping_line'))
            ->first();
    }

    /**
     * @param  list<ParsedRow>  $group
     */
    private function createShipment(array $group, ShipmentStatus $status, Actor $actor): Shipment
    {
        $first = $group[0];
        $arrived = $status->isAtLeast(ShipmentStatus::Arrived);

        $arrivalDates = collect($group)->map(fn (ParsedRow $r) => $r->ata)->filter()->unique(fn ($d) => $d->toDateString());

        $shipment = $this->shipments->create([
            'status' => $status->value,
            'status_note' => 'Imported from delivery plan ("'.$first->statusLabel.'")',
            'shipping_method' => $first->method->value,
            'shipping_line' => $first->shippingLine,
            'origin_port' => $first->method->value === 'air' ? 'Mumbai (BOM)' : 'Nhava Sheva (Mumbai)',
            'destination_port' => $first->destinationPort,
            'incoterm' => $first->incoterm,
            'etd' => $first->etd,
            'eta' => $first->eta,
            'ata' => $arrived ? $arrivalDates->sort()->first() : null,
        ], $actor);

        $this->shipmentsCreated++;

        if ($arrived && $arrivalDates->count() > 1) {
            foreach ($group as $row) {
                $row->warn('ata', sprintf(
                    'Rows of this shipment disagree on the arrival date (%s); the earliest was used.',
                    $arrivalDates->map->toDateString()->sort()->implode(', ')
                ));
            }
        }

        // Still at sea: the "Arrival date/Actual ETA" column is a revised ETA, kept as an audited change.
        if (! $arrived && $arrivalDates->isNotEmpty()) {
            $revised = $arrivalDates->sort()->last();
            if ($revised->ne($first->eta)) {
                $this->dates->changeShipmentDates(
                    $shipment,
                    [DateField::Eta->value => $revised],
                    'Revised ETA from delivery plan (planned '.$first->eta->toDateString().', latest estimate '.$revised->toDateString().')',
                    $actor,
                );
            }
        }

        return $shipment;
    }

    /**
     * @param  list<ParsedRow>  $rows  rows of this container
     */
    private function findOrCreateContainer(Shipment $shipment, string $containerNo, array $rows): Container
    {
        $existing = $shipment->containers()->where('container_no', $containerNo)->first();
        if ($existing) {
            return $existing;
        }

        $facilities = collect($rows)->map(fn (ParsedRow $r) => $r->storageFacility)->filter()->unique();
        if ($facilities->count() > 1) {
            foreach ($rows as $row) {
                $row->warn('storage_facility', 'Rows of container '.$containerNo.' name different storage facilities ('.$facilities->implode(', ').'); the first was used.');
            }
        }

        $container = $this->shipments->addContainer($shipment, [
            'container_no' => $containerNo,
            'container_type' => collect($rows)->map(fn (ParsedRow $r) => $r->containerType)->filter()->first(),
            'customs_cleared' => collect($rows)->contains(fn (ParsedRow $r) => $r->customsCleared),
            'storage_facility' => $facilities->first(),
            'storage_date' => collect($rows)->map(fn (ParsedRow $r) => $r->storageDate)->filter()->sort()->first(),
            'pickup_date' => collect($rows)->map(fn (ParsedRow $r) => $r->pickupDate)->filter()->sort()->first(),
        ]);

        $this->containersCreated++;

        return $container;
    }

    private function createItem(Container $container, ParsedRow $row, ShipmentStatus $shipmentStatus): void
    {
        $deliveredAt = null;

        if ($row->status === ShipmentStatus::Delivered) {
            $deliveredAt = $row->customerDeliveryDate ?? $row->storageDate ?? $row->ata;
            if ($row->customerDeliveryDate === null) {
                $row->warn('customer_delivery', $deliveredAt
                    ? 'Delivered without a delivery date; the storage/arrival date '.$deliveredAt->toDateString().' was used.'
                    : 'Delivered without any usable date; delivered date left empty.');
            }
        } elseif ($row->status->order() > $shipmentStatus->order()) {
            $row->warn('status', sprintf(
                'Row status "%s" is ahead of its container (%s); the item follows the container.',
                $row->statusLabel, $shipmentStatus->label()
            ));
        }

        $this->shipments->addCargoItem($container, [
            'customer_name' => $row->customerName,
            'product' => [
                'material_code' => $row->materialCode,
                'description' => $row->description,
                'net_type' => $row->netTypeLabel,
            ],
            'proforma_invoice_no' => $row->invoiceNo,
            'exporter_ref' => $row->salesOrderNo,
            'customer_po' => $row->customerPo,
            'tag_no' => $row->tagNo,
            'package_no' => $row->packageNo,
            'quantity' => $row->quantity,
            'unit' => $row->unit,
            'net_weight_kg' => $row->netWeight,
            'gross_weight_kg' => $row->grossWeight,
            'is_stock' => $row->isStock,
            'certificate_sent' => $row->certificateSent,
            'customer_delivery_date' => $row->approvedDeliveryDate ?? $row->customerDeliveryDate,
            'delivered_at' => $deliveredAt,
            'comments' => $row->comments,
        ]);

        $this->itemsCreated++;
    }

    private function issue(ParsedRow $row, string $level, string $column, string $message): void
    {
        $this->issues[] = [
            'row_number' => $row->rowNumber,
            'level' => $level,
            'column' => $column,
            'message' => $message,
            'raw' => $row->raw,
        ];
    }

    private function persistIssues(ImportRun $run): void
    {
        $now = CarbonImmutable::now();

        foreach (array_chunk($this->issues, 200) as $chunk) {
            ImportIssue::insert(array_map(fn ($issue) => [
                ...$issue,
                'raw' => json_encode($issue['raw']),
                'import_run_id' => $run->id,
                'created_at' => $now,
            ], $chunk));
        }
    }
}
