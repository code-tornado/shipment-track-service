<?php

namespace App\Import;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingMethod;
use Carbon\CarbonImmutable;

/**
 * One data row of the delivery plan after normalisation, with everything
 * that was wrong (errors: row is skipped) or merely odd (warnings: row is
 * imported) about it.
 */
final class ParsedRow
{
    /** @var list<array{column: string, message: string}> */
    public array $errors = [];

    /** @var list<array{column: string, message: string}> */
    public array $warnings = [];

    public ?string $customerName = null;

    public ?string $customerPo = null;

    public ?string $invoiceNo = null;

    public ?string $salesOrderNo = null;

    public ?string $materialCode = null;

    public ?string $netTypeLabel = null;

    public ?string $description = null;

    public int $quantity = 1;

    public string $unit = 'PC';

    public ?float $netWeight = null;

    public ?float $grossWeight = null;

    public ?string $packageNo = null;

    public ?string $tagNo = null;

    public bool $certificateSent = false;

    public ShippingMethod $method = ShippingMethod::Sea;

    public ?string $destinationPort = null;

    public ?string $containerNo = null;

    public ?string $containerType = null;

    public ?string $shippingLine = null;

    public ?string $incoterm = null;

    public ?CarbonImmutable $etd = null;

    public ?CarbonImmutable $eta = null;

    public ?CarbonImmutable $ata = null;

    public ?CarbonImmutable $approvedDeliveryDate = null;

    public ?CarbonImmutable $customerDeliveryDate = null;

    public ?CarbonImmutable $pickupDate = null;

    public ?CarbonImmutable $storageDate = null;

    public ?string $storageFacility = null;

    public bool $customsCleared = false;

    public ?ShipmentStatus $status = null;

    public ?string $statusLabel = null;

    public bool $isStock = false;

    public ?string $comments = null;

    /** Set when the row was skipped for a non-error reason (already imported). */
    public ?string $skippedBecause = null;

    /**
     * @param  array<string, mixed>  $raw  compact snapshot of the source cells
     */
    public function __construct(
        public readonly int $rowNumber,
        public readonly array $raw,
    ) {}

    public function error(string $column, string $message): void
    {
        $this->errors[] = ['column' => $column, 'message' => $message];
    }

    public function warn(string $column, string $message): void
    {
        $this->warnings[] = ['column' => $column, 'message' => $message];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function isValid(): bool
    {
        return $this->errors === [] && $this->skippedBecause === null;
    }

    /** Rows with the same key travel on the same sailing: one shipment. */
    public function shipmentKey(ShipmentStatus $containerStatus): string
    {
        return implode('|', [
            $this->method->value,
            mb_strtolower((string) $this->shippingLine),
            mb_strtolower((string) $this->destinationPort),
            $this->etd?->toDateString(),
            $this->eta?->toDateString(),
            $containerStatus->value,
        ]);
    }
}
