<?php

namespace App\Console\Commands;

use App\Import\DeliveryPlanImporter;
use App\Models\Company;
use App\Models\ImportRun;
use App\Models\User;
use App\Tenancy\CurrentCompany;
use Illuminate\Console\Command;

class ImportDeliveryPlanCommand extends Command
{
    protected $signature = 'import:delivery-plan
                            {path : Path to the .xlsx file}
                            {--company= : Company id or slug (default: the first company)}
                            {--user= : Email of the user to record as importer}
                            {--issues : Print every warning, not only errors}';

    protected $description = 'Import a delivery plan spreadsheet into a company';

    public function handle(DeliveryPlanImporter $importer, CurrentCompany $current): int
    {
        $path = $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $company = $this->option('company')
            ? Company::query()->where('id', $this->option('company'))->orWhere('slug', $this->option('company'))->first()
            : Company::query()->orderBy('id')->first();

        if (! $company) {
            $this->error('Company not found.');

            return self::FAILURE;
        }

        $run = $current->runAs($company, function () use ($importer, $path, $company) {
            $user = $this->option('user') ? User::query()->where('email', $this->option('user'))->first() : null;
            $this->info(sprintf('Importing %s into %s…', basename($path), $company->name));

            return $importer->import($path, $user);
        });

        $this->printSummary($run);

        return $run->status === ImportRun::STATUS_COMPLETED ? self::SUCCESS : self::FAILURE;
    }

    private function printSummary(ImportRun $run): void
    {
        if ($run->status === ImportRun::STATUS_FAILED) {
            $this->error('Import failed: '.$run->error);

            return;
        }

        $this->table(['Rows', 'Imported', 'Skipped', 'Warnings', 'Shipments', 'Containers', 'Cargo items'], [[
            $run->rows_total, $run->rows_imported, $run->rows_skipped, $run->warnings_count,
            $run->shipments_created, $run->containers_created, $run->cargo_items_created,
        ]]);

        $issues = $run->issues()->when(! $this->option('issues'), fn ($q) => $q->where('level', 'error'))->get();

        if ($issues->isNotEmpty()) {
            $this->table(
                ['Row', 'Level', 'Column', 'Message'],
                $issues->map(fn ($i) => [$i->row_number, $i->level, $i->column, $i->message])->all()
            );
        }

        if (! $this->option('issues') && $run->warnings_count > 0) {
            $this->line("Run with --issues to list the {$run->warnings_count} warning(s).");
        }
    }
}
