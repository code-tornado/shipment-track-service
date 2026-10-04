<?php

namespace Database\Seeders;

use App\Import\DeliveryPlanImporter;
use App\Models\Company;
use App\Models\Shipment;
use App\Models\User;
use App\Tenancy\CurrentCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Two companies with one user and one known API token each, and the
 * delivery plan from the task imported into the first one. The second
 * company stays empty so data separation can be seen from the UI.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $garware = $this->company(
            'garware-no', 'Garware Technical Fibres AS (Norway)',
            'demo@garware.example', 'Demo User', env('DEMO_API_TOKEN', 'demo-token-garware')
        );

        $this->company(
            'demo-fish', 'Demo Fish Farms AS',
            'demo@fishfarms.example', 'Other User', env('DEMO_API_TOKEN_OTHER', 'demo-token-other')
        );

        app(CurrentCompany::class)->runAs($garware, function () use ($garware) {
            if (Shipment::query()->exists()) {
                $this->command?->line('Delivery plan already imported for '.$garware->name.'.');

                return;
            }

            $path = database_path('seed/Norway_delivery_plan.xlsx');
            $run = app(DeliveryPlanImporter::class)->import($path, $garware->users()->first(), basename($path));

            $this->command?->line(sprintf(
                'Imported delivery plan: %d rows, %d imported, %d skipped, %d warnings → %d shipments, %d containers, %d cargo items.',
                $run->rows_total, $run->rows_imported, $run->rows_skipped, $run->warnings_count,
                $run->shipments_created, $run->containers_created, $run->cargo_items_created
            ));
        });
    }

    private function company(string $slug, string $name, string $email, string $userName, string $plainToken): Company
    {
        $company = Company::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            ['company_id' => $company->id, 'name' => $userName, 'password' => Hash::make('password')]
        );

        $token = $user->tokens()->firstOrCreate(
            ['name' => 'demo'],
            ['token' => hash('sha256', $plainToken), 'abilities' => ['*']]
        );

        $this->command?->info(sprintf(
            '%s — login %s / password — API token: %d|%s',
            $company->name, $email, $token->id, $plainToken
        ));

        return $company;
    }
}
