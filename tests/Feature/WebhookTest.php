<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Shipment;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * This is how Suppli gets told about status and date changes.
 */
class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_and_date_changes_are_posted_to_registered_endpoints_with_a_signature(): void
    {
        Http::fake(['https://suppli.example/*' => Http::response(['ok' => true], 200)]);

        $user = $this->actingAsCompanyUser();
        $secret = 'shared-secret-for-suppli-123';

        $this->postJson('/api/v1/webhooks', ['url' => 'https://suppli.example/hooks/shipping', 'secret' => $secret, 'events' => ['*']])
            ->assertCreated()->assertJsonPath('data.secret', $secret);

        // Another company's endpoint must not receive our events.
        WebhookEndpoint::withoutGlobalScopes()->create(['company_id' => Company::factory()->create()->id, 'url' => 'https://other.example/hook', 'secret' => 'x', 'events' => ['*']]);

        $shipment = Shipment::factory()->for($user->company)->create(['status' => 'in_transit', 'etd' => '2026-08-08', 'eta' => '2026-09-17']);

        $this->postJson("/api/v1/shipments/{$shipment->id}/status", ['status' => 'arrived', 'note' => 'Docked'])->assertOk();
        $this->patchJson("/api/v1/shipments/{$shipment->id}/dates", ['eta' => '2026-09-20', 'reason' => 'Port congestion'])->assertOk();

        // arrival (ata set) + status change + eta change
        Http::assertSentCount(3);
        Http::assertSent(function (Request $request) use ($secret) {
            if ($request->header('X-Webhook-Event')[0] !== 'shipment.status_changed') {
                return false;
            }
            $body = $request->body();
            $payload = json_decode($body, true);

            return $request->url() === 'https://suppli.example/hooks/shipping'
                && $request->header('X-Webhook-Signature')[0] === 'sha256='.hash_hmac('sha256', $body, $secret)
                && $payload['data']['change']['from'] === 'in_transit'
                && $payload['data']['change']['to'] === 'arrived'
                && $payload['data']['change']['note'] === 'Docked'
                && $payload['data']['shipment']['reference'] === $payload['data']['shipment']['reference'];
        });
        Http::assertSent(function (Request $request) {
            $payload = json_decode($request->body(), true);

            return $request->header('X-Webhook-Event')[0] === 'shipment.dates_changed'
                && collect($payload['data']['changes'])->contains(fn ($c) => $c['field'] === 'eta' && $c['old'] === '2026-09-17' && $c['new'] === '2026-09-20' && $c['reason'] === 'Port congestion');
        });
        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://other.example'));

        $this->assertSame(3, WebhookDelivery::where('status', WebhookDelivery::STATUS_SENT)->where('response_code', 200)->count());

        $endpointId = $this->getJson('/api/v1/webhooks')->assertOk()->assertJsonCount(1, 'data')->json('data.0.id');
        $this->getJson("/api/v1/webhooks/{$endpointId}/deliveries")->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_failed_deliveries_are_recorded(): void
    {
        Http::fake(['https://suppli.example/*' => Http::response('nope', 500)]);

        $user = $this->actingAsCompanyUser();
        $this->postJson('/api/v1/webhooks', ['url' => 'https://suppli.example/hooks/shipping', 'events' => ['shipment.status_changed']])->assertCreated();

        $shipment = Shipment::factory()->for($user->company)->create(['status' => 'in_transit']);
        $this->postJson("/api/v1/shipments/{$shipment->id}/status", ['status' => 'arrived'])->assertOk();

        // Only the subscribed event is delivered, and it is marked failed.
        $this->assertSame(1, WebhookDelivery::count());
        $this->assertSame(WebhookDelivery::STATUS_FAILED, WebhookDelivery::first()->status);
        $this->assertSame(500, WebhookDelivery::first()->response_code);
    }
}
