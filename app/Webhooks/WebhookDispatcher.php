<?php

namespace App\Webhooks;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Carbon\CarbonImmutable;

/**
 * Fans an event out to every active endpoint of the company that subscribed
 * to it. One delivery row per endpoint, sent by a queued job.
 */
class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $data
     * @return list<WebhookDelivery>
     */
    public function dispatch(int $companyId, string $event, array $data, ?CarbonImmutable $occurredAt = null): array
    {
        $endpoints = WebhookEndpoint::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint) => $endpoint->listensTo($event));

        $deliveries = [];

        foreach ($endpoints as $endpoint) {
            $delivery = $endpoint->deliveries()->create([
                'event' => $event,
                'status' => WebhookDelivery::STATUS_PENDING,
                'payload' => [
                    'event' => $event,
                    'occurred_at' => ($occurredAt ?? CarbonImmutable::now())->toIso8601String(),
                    'company_id' => $companyId,
                    'data' => $data,
                ],
            ]);

            SendWebhookDelivery::dispatch($delivery->id);
            $deliveries[] = $delivery;
        }

        return $deliveries;
    }
}
