<?php

namespace App\Tracking;

use App\Models\CargoItem;
use App\Models\Container;

/**
 * In-memory provider for tests and demos: updates are queued per container
 * number / tag number and handed out when asked.
 */
class FakeTrackingProvider implements TrackingProvider
{
    /** @var array<string, TrackingUpdate> */
    private array $containerUpdates = [];

    /** @var array<string, DeliveryUpdate> */
    private array $deliveryUpdates = [];

    /** @var list<string> */
    public array $queriedContainers = [];

    public function name(): string
    {
        return 'fake';
    }

    public function willReportContainer(string $containerNo, TrackingUpdate $update): static
    {
        $this->containerUpdates[strtoupper($containerNo)] = $update;

        return $this;
    }

    public function willReportDelivery(string $tagOrPackageNo, DeliveryUpdate $update): static
    {
        $this->deliveryUpdates[strtoupper($tagOrPackageNo)] = $update;

        return $this;
    }

    public function trackContainer(Container $container): ?TrackingUpdate
    {
        $this->queriedContainers[] = $container->container_no;

        return $this->containerUpdates[strtoupper($container->container_no)] ?? null;
    }

    public function trackDelivery(CargoItem $item): ?DeliveryUpdate
    {
        foreach ([$item->tag_no, $item->package_no] as $key) {
            if ($key !== null && isset($this->deliveryUpdates[strtoupper($key)])) {
                return $this->deliveryUpdates[strtoupper($key)];
            }
        }

        return null;
    }
}
