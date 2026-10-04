<?php

namespace App\Providers;

use App\Events\CargoItemDatesChanged;
use App\Events\ShipmentDatesChanged;
use App\Events\ShipmentStatusChanged;
use App\Models\CargoItem;
use App\Models\Shipment;
use App\Models\User;
use App\Tenancy\CurrentCompany;
use App\Tracking\FakeTrackingProvider;
use App\Tracking\ManualTrackingProvider;
use App\Tracking\TrackingProvider;
use App\Webhooks\NotifyWebhooks;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentCompany::class);

        // The tracking provider is swappable: manual entry by default, a fake
        // one in tests, a Shipmondo / container-tracking adapter later.
        $this->app->singleton(TrackingProvider::class, function () {
            return match (config('tracking.provider')) {
                'fake' => new FakeTrackingProvider,
                default => new ManualTrackingProvider,
            };
        });
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'shipment' => Shipment::class,
            'cargo_item' => CargoItem::class,
            'user' => User::class,
        ]);

        Event::listen(ShipmentStatusChanged::class, [NotifyWebhooks::class, 'handleStatusChanged']);
        Event::listen(ShipmentDatesChanged::class, [NotifyWebhooks::class, 'handleShipmentDatesChanged']);
        Event::listen(CargoItemDatesChanged::class, [NotifyWebhooks::class, 'handleCargoItemDatesChanged']);
    }
}
