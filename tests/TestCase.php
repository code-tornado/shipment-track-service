<?php

namespace Tests;

use App\Models\Company;
use App\Models\User;
use App\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate as a user of the given (or a new) company for API calls,
     * and set the company context for direct model/service calls in the test.
     */
    protected function actingAsCompanyUser(?Company $company = null): User
    {
        $user = User::factory()->for($company ?? Company::factory())->create();

        Sanctum::actingAs($user);
        app(CurrentCompany::class)->set($user->company);

        return $user;
    }

    protected function runAsCompany(Company $company, callable $callback): mixed
    {
        return app(CurrentCompany::class)->runAs($company, $callback);
    }

    protected function seedFile(): string
    {
        return database_path('seed/Norway_delivery_plan.xlsx');
    }
}
