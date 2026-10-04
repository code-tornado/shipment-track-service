<?php

namespace App\Webhooks;

use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * POSTs one delivery. The body is signed with the endpoint secret
 * (X-Webhook-Signature: sha256=<hmac>) so the receiver can verify origin.
 */
class SendWebhookDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> seconds between attempts */
    public array $backoff = [30, 120, 600, 3600];

    public function __construct(public readonly int $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('endpoint')->find($this->deliveryId);

        if (! $delivery || ! $delivery->endpoint) {
            return;
        }

        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $body, $delivery->endpoint->secret);

        $delivery->attempts++;
        $delivery->last_attempt_at = now();

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Event' => $delivery->event,
                    'X-Webhook-Delivery' => (string) $delivery->id,
                    'X-Webhook-Signature' => 'sha256='.$signature,
                ])
                ->withBody($body, 'application/json')
                ->post($delivery->endpoint->url);

            $delivery->response_code = $response->status();
            $delivery->status = $response->successful() ? WebhookDelivery::STATUS_SENT : WebhookDelivery::STATUS_FAILED;
            $delivery->save();

            if (! $response->successful() && $this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 3600);
            }
        } catch (Throwable $e) {
            $delivery->status = WebhookDelivery::STATUS_FAILED;
            $delivery->save();

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 3600);
            }
        }
    }
}
