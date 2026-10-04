<?php

namespace App\Console\Commands;

use App\Enums\ShipmentStatus;
use App\Models\Company;
use App\Models\Shipment;
use App\Tenancy\CurrentCompany;
use App\Tracking\TrackingProvider;
use App\Tracking\TrackingSyncService;
use Illuminate\Console\Command;

class TrackingSyncCommand extends Command
{
    protected $signature = 'tracking:sync
                            {--company= : Only this company (id or slug)}
                            {--shipment= : Only this shipment reference}';

    protected $description = 'Pull status and date updates from the configured tracking provider';

    public function handle(TrackingSyncService $sync, TrackingProvider $provider, CurrentCompany $current): int
    {
        $this->info('Provider: '.$provider->name());

        $companies = Company::query()
            ->when($this->option('company'), fn ($q, $c) => $q->where('id', $c)->orWhere('slug', $c))
            ->get();

        foreach ($companies as $company) {
            $current->runAs($company, function () use ($company, $sync) {
                $shipments = Shipment::query()
                    ->where('status', '!=', ShipmentStatus::Delivered)
                    ->when($this->option('shipment'), fn ($q, $ref) => $q->where('reference', $ref))
                    ->get();

                foreach ($shipments as $shipment) {
                    $summary = $sync->syncShipment($shipment);
                    $total = array_sum($summary);
                    if ($total > 0) {
                        $this->line(sprintf(
                            '[%s] %s: %d status, %d date, %d delivery change(s)',
                            $company->slug, $shipment->reference,
                            $summary['status_changes'], $summary['date_changes'], $summary['delivery_changes']
                        ));
                    }
                }
            });
        }

        return self::SUCCESS;
    }
}
