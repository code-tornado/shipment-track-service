<?php

namespace App\Import;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingMethod;
use App\Models\CargoItem;
use App\Rules\ContainerNumber;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Turns one spreadsheet row into a ParsedRow. Knows which columns exist in
 * the delivery plan (matched by header text, so column order does not
 * matter) and how to clean each value.
 */
class RowParser
{
    /**
     * Canonical column => accepted header texts (lower-case, trimmed, no trailing colon).
     * The first match wins, so the most specific spelling comes first.
     */
    public const COLUMNS = [
        'customer' => ['customer name', 'customer'],
        'customer_po' => ['customer po', 'customer po no'],
        'invoice' => ['invoice no', 'invoice no.', 'proforma invoice no', 'invoice number', 'invoice'],
        'sales_order' => ['sales order no', 'sales order no.', "exporter's reference", 'exporters reference', 'exporter ref'],
        'material_code' => ['material code', 'product code'],
        'net_type' => ['net type', 'product type'],
        'description' => ['material description', 'product description', 'description'],
        'quantity' => ['qty', 'quantity'],
        'unit' => ['unit', 'uom'],
        'net_weight' => ['net w kgs', 'net weight kgs', 'net weight', 'net w'],
        'gross_weight' => ['gross w kgs', 'gross weight kgs', 'gross weight', 'gross w'],
        'package' => ['batch no.', 'batch no', 'package no.', 'package no'],
        'tag' => ['tagno - gnp', 'tag no', 'tagno', 'tag number', 'tag'],
        'certificate' => ['certificate sent to cust', 'certificate sent'],
        'method' => ['shipping method', 'transport mode'],
        'port' => ['destination port', 'destination'],
        'container' => ['container/ awb no', 'container/awb no', 'container no', 'container no.', 'container'],
        'container_type' => ['cont type', 'container type'],
        'line' => ['shipping line', 'carrier'],
        'incoterm' => ['incoterm', 'incoterms'],
        'etd' => ['etd mumbai', 'etd'],
        'eta' => ['planned eta', 'eta'],
        'ata' => ['arrival date/actual eta port', 'arrival date/ actual eta port', 'arrival date', 'actual eta', 'ata'],
        'approved_delivery' => ['customer approved del date', 'customer approved delivery date'],
        'status' => ['current status', 'status'],
        'customs' => ['customs cleared'],
        'pickup' => ['pickup date'],
        'storage_date' => ['storage date'],
        'storage_facility' => ['storage facility'],
        'customer_delivery' => ['cust delivery date', 'customer delivery date'],
        'comments_site' => ['comments/ site', 'comments/site', 'comments / site'],
        'comments' => ['comments', 'comment'],
    ];

    public const REQUIRED = ['customer', 'invoice', 'description', 'container', 'etd', 'eta', 'status'];

    /** @var array<string, int> canonical column => cell index */
    private array $columns = [];

    /**
     * @param  list<mixed>  $headerRow
     * @return list<string> missing required columns
     */
    public function mapHeader(array $headerRow): array
    {
        $normalized = array_map(fn ($cell) => self::normalizeHeader($cell), $headerRow);

        foreach (self::COLUMNS as $key => $candidates) {
            foreach ($candidates as $candidate) {
                $index = array_search($candidate, $normalized, true);
                if ($index !== false) {
                    $this->columns[$key] = $index;
                    break;
                }
            }
        }

        return array_values(array_filter(self::REQUIRED, fn ($key) => ! isset($this->columns[$key])));
    }

    public static function normalizeHeader(mixed $cell): string
    {
        $text = mb_strtolower(trim((string) $cell));
        $text = preg_replace('/\s+/', ' ', $text);

        return rtrim($text, ':');
    }

    public static function isHeaderRow(array $row): bool
    {
        return in_array('customer name', array_map(fn ($c) => self::normalizeHeader($c), $row), true);
    }

    public static function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<mixed>  $cells
     */
    public function parse(int $rowNumber, array $cells): ParsedRow
    {
        $get = fn (string $key) => isset($this->columns[$key]) ? ($cells[$this->columns[$key]] ?? null) : null;

        $row = new ParsedRow($rowNumber, [
            'customer' => self::str($get('customer')),
            'invoice' => self::str($get('invoice')),
            'container' => self::str($get('container')),
            'tag' => self::str($get('tag')),
            'package' => self::str($get('package')),
            'description' => self::str($get('description')),
            'status' => self::str($get('status')),
        ]);

        // --- Suppli references -------------------------------------------------
        $row->customerName = self::str($get('customer'));
        if ($row->customerName === null) {
            $row->error('customer', 'Customer name is empty.');
        }

        $row->customerPo = self::str($get('customer_po'));
        $row->invoiceNo = self::str($get('invoice'));
        if ($row->invoiceNo === null) {
            $row->error('invoice', 'Invoice number is empty.');
        }
        $row->salesOrderNo = self::str($get('sales_order'));

        $row->materialCode = self::str($get('material_code'));
        $row->netTypeLabel = self::str($get('net_type'));
        $row->description = self::str($get('description'));
        if ($row->description === null) {
            $row->error('description', 'Material description is empty.');
        } elseif ($row->materialCode === null) {
            $row->warn('material_code', 'No material code; product matched by description.');
        }

        // --- Quantities ------------------------------------------------------
        $qty = $get('quantity');
        if ($qty === null || trim((string) $qty) === '') {
            $row->quantity = 1;
            $row->warn('quantity', 'Quantity is empty; assumed 1.');
        } elseif (! is_numeric($qty) || (int) $qty < 1 || (float) $qty != (int) $qty) {
            $row->error('quantity', sprintf('Quantity "%s" is not a positive whole number.', $qty));
        } else {
            $row->quantity = (int) $qty;
        }
        $row->unit = self::str($get('unit')) ?? 'PC';

        $row->netWeight = self::number($get('net_weight'));
        $row->grossWeight = self::number($get('gross_weight'));
        if ($get('net_weight') !== null && $row->netWeight === null) {
            $row->warn('net_weight', 'Net weight is not a number; ignored.');
        }
        if ($get('gross_weight') !== null && $row->grossWeight === null) {
            $row->warn('gross_weight', 'Gross weight is not a number; ignored.');
        }

        $row->packageNo = self::str($get('package'));
        if ($row->packageNo === null) {
            $row->warn('package', 'No package/batch number; a re-import cannot recognise this row.');
        }

        $row->tagNo = CargoItem::normalizeTag(self::str($get('tag')));
        if ($row->tagNo === null) {
            $row->warn('tag', 'No tag number.');
        }

        $row->certificateSent = self::yes($get('certificate'));

        // --- Transport ---------------------------------------------------------
        $methodLabel = self::str($get('method'));
        if ($methodLabel === null) {
            $row->method = ShippingMethod::Sea;
            $row->warn('method', 'Shipping method is empty; assumed Sea.');
        } else {
            $method = ShippingMethod::tryFromLabel($methodLabel);
            if ($method === null) {
                $row->error('method', sprintf('Unknown shipping method "%s".', $methodLabel));
            } else {
                $row->method = $method;
            }
        }

        $row->destinationPort = self::str($get('port'));
        if ($row->destinationPort === null) {
            $row->error('port', 'Destination port is empty.');
        } else {
            $row->destinationPort = mb_strtoupper($row->destinationPort);
        }

        $container = self::str($get('container'));
        if ($container === null) {
            $row->error('container', 'Container / AWB number is empty.');
        } else {
            $container = ContainerNumber::normalize($container, $row->method);
            $valid = $row->method === ShippingMethod::Air
                ? ContainerNumber::isValidAirWaybill($container)
                : ContainerNumber::isValidIso6346($container);
            if (! $valid) {
                $row->error('container', sprintf(
                    '"%s" is not a valid %s.',
                    $container,
                    $row->method === ShippingMethod::Air ? 'air waybill number' : 'ISO 6346 container number'
                ));
            }
            $row->containerNo = $container;
        }

        $row->containerType = self::str($get('container_type'));
        $row->shippingLine = self::str($get('line'));
        $row->incoterm = self::str($get('incoterm'));

        // --- Dates -------------------------------------------------------------
        $row->etd = $this->date($row, $get('etd'), 'etd', required: true);
        $row->eta = $this->date($row, $get('eta'), 'eta', required: true);
        if ($row->etd && $row->eta && $row->eta->lt($row->etd)) {
            $row->error('eta', sprintf('Planned ETA %s is before ETD %s.', $row->eta->toDateString(), $row->etd->toDateString()));
        }
        $row->ata = $this->date($row, $get('ata'), 'ata');
        if ($row->etd && $row->ata && $row->ata->lt($row->etd)) {
            $row->warn('ata', 'Arrival date is before ETD; ignored.');
            $row->ata = null;
        }
        $row->approvedDeliveryDate = $this->date($row, $get('approved_delivery'), 'approved_delivery');
        $row->customerDeliveryDate = $this->date($row, $get('customer_delivery'), 'customer_delivery');
        $row->pickupDate = $this->date($row, $get('pickup'), 'pickup');
        $row->storageDate = $this->date($row, $get('storage_date'), 'storage_date');
        $row->storageFacility = self::str($get('storage_facility'));
        $row->customsCleared = self::yes($get('customs'));

        // --- Status ------------------------------------------------------------
        $row->statusLabel = self::str($get('status'));
        $status = ShipmentStatus::tryFromLabel($row->statusLabel);
        if ($status === null) {
            $row->error('status', $row->statusLabel === null
                ? 'Status is empty.'
                : sprintf('Unknown status "%s".', $row->statusLabel));
        } else {
            $row->status = $status;
        }

        $row->isStock = str_contains(mb_strtolower((string) $row->statusLabel), 'stock')
            || mb_strtolower((string) $row->customerPo) === 'stock';
        if ($row->isStock && $status === ShipmentStatus::InStorage) {
            $row->warn('status', sprintf('Status "%s" imported as In storage with the stock flag set.', $row->statusLabel));
        }

        $comments = array_filter([self::str($get('comments_site')), self::str($get('comments'))]);
        $row->comments = $comments ? implode(' | ', $comments) : null;

        return $row;
    }

    /** Cell to trimmed string; integral floats (Excel numbers) lose their ".0". */
    public static function str(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_float($value) && floor($value) == $value) {
            $value = (string) (int) $value;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public static function number(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $normalized = str_replace([' ', ','], ['', '.'], (string) $value);

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    public static function yes(mixed $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value)), ['ok', 'yes', 'y', 'true', '1', 'x', 'done'], true);
    }

    private function date(ParsedRow $row, mixed $value, string $column, bool $required = false): ?CarbonImmutable
    {
        if ($value === null || trim((string) $value) === '') {
            if ($required) {
                $row->error($column, ucfirst(str_replace('_', ' ', $column)).' is empty.');
            }

            return null;
        }

        $parsed = self::parseDate($value);

        if ($parsed === null) {
            if ($required) {
                $row->error($column, sprintf('"%s" is not a date.', $value));
            } else {
                $row->warn($column, sprintf('"%s" is not a date; ignored.', $value));
            }
        }

        return $parsed;
    }

    public static function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value) && (float) $value > 20000)) {
            try {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            } catch (Throwable) {
                return null;
            }
        }

        $text = trim((string) $value);
        foreach (['d.m.Y', 'd.m.y', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'Y-m-d H:i:s'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $text);
            } catch (Throwable) {
                continue;
            }
            if ($date !== false && $date->format($format) === $text) {
                return $date->startOfDay();
            }
        }

        try {
            return CarbonImmutable::parse($text)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
