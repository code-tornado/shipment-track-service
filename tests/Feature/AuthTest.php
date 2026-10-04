<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_without_a_token_are_rejected(): void
    {
        $this->getJson('/api/v1/shipments')->assertUnauthorized();
        $this->getJson('/api/v1/reports/status-overview')->assertUnauthorized();
    }

    public function test_a_token_can_be_issued_with_email_and_password_and_used(): void
    {
        $user = User::factory()->create(['email' => 'lisa@example.com', 'password' => 'secret-123']);

        $response = $this->postJson('/api/v1/auth/token', [
            'email' => 'lisa@example.com',
            'password' => 'secret-123',
            'device_name' => 'tests',
        ])->assertCreated()->assertJsonPath('user.company.id', $user->company_id);

        $token = $response->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'lisa@example.com')
            ->assertJsonPath('user.company.name', $user->company->name);

        $this->withToken($token)->getJson('/api/v1/shipments')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'lisa@example.com', 'password' => 'secret-123']);

        $this->postJson('/api/v1/auth/token', ['email' => 'lisa@example.com', 'password' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_a_revoked_token_stops_working(): void
    {
        $user = User::factory()->create(['password' => 'secret-123']);
        $token = $this->postJson('/api/v1/auth/token', ['email' => $user->email, 'password' => 'secret-123'])->json('token');

        $this->withToken($token)->deleteJson('/api/v1/auth/token')->assertOk();

        // The guard caches the resolved user for the lifetime of the test app; drop it like a new request would.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
