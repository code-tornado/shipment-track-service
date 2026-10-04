<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebhookEndpointRequest;
use App\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Where to send status / date change notifications (e.g. Suppli's inbound endpoint).
 */
class WebhookEndpointController extends Controller
{
    public function index(): JsonResponse
    {
        $endpoints = WebhookEndpoint::query()->withCount('deliveries')->orderBy('id')->get();

        return response()->json([
            'data' => $endpoints->map(fn (WebhookEndpoint $e) => $this->payload($e)),
        ]);
    }

    public function store(StoreWebhookEndpointRequest $request): JsonResponse
    {
        $data = $request->validated();
        $secret = $data['secret'] ?? Str::random(40);

        $endpoint = WebhookEndpoint::create([
            'url' => $data['url'],
            'secret' => $secret,
            'events' => $data['events'] ?? ['*'],
            'active' => $data['active'] ?? true,
        ]);

        // The secret is only shown once, on creation.
        return response()->json(['data' => [...$this->payload($endpoint), 'secret' => $secret]], 201);
    }

    public function destroy(WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $webhookEndpoint->delete();

        return response()->json(null, 204);
    }

    public function deliveries(WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $deliveries = $webhookEndpoint->deliveries()->orderByDesc('id')->limit(100)->get();

        return response()->json([
            'data' => $deliveries->map(fn ($d) => [
                'id' => $d->id,
                'event' => $d->event,
                'status' => $d->status,
                'response_code' => $d->response_code,
                'attempts' => $d->attempts,
                'last_attempt_at' => $d->last_attempt_at?->toIso8601String(),
                'payload' => $d->payload,
            ]),
        ]);
    }

    private function payload(WebhookEndpoint $endpoint): array
    {
        return [
            'id' => $endpoint->id,
            'url' => $endpoint->url,
            'events' => $endpoint->events,
            'active' => $endpoint->active,
            'deliveries_count' => $endpoint->deliveries_count ?? null,
            'created_at' => $endpoint->created_at?->toIso8601String(),
        ];
    }
}
