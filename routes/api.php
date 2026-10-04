<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CargoItemController;
use App\Http\Controllers\Api\CargoItemDateController;
use App\Http\Controllers\Api\ContainerController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\ShipmentDateController;
use App\Http\Controllers\Api\ShipmentHistoryController;
use App\Http\Controllers\Api\ShipmentStatusController;
use App\Http\Controllers\Api\WebhookEndpointController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/token', [AuthController::class, 'issueToken']);

    Route::middleware(['auth:sanctum', 'company'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::delete('auth/token', [AuthController::class, 'revokeToken']);

        // Shipments and what they carry
        Route::apiResource('shipments', ShipmentController::class);
        Route::post('shipments/{shipment}/status', [ShipmentStatusController::class, 'store']);
        Route::patch('shipments/{shipment}/dates', [ShipmentDateController::class, 'update']);
        Route::get('shipments/{shipment}/history', [ShipmentHistoryController::class, 'index']);

        Route::post('shipments/{shipment}/containers', [ContainerController::class, 'store']);
        Route::patch('containers/{container}', [ContainerController::class, 'update']);
        Route::delete('containers/{container}', [ContainerController::class, 'destroy']);

        Route::post('containers/{container}/cargo-items', [CargoItemController::class, 'store']);
        Route::patch('cargo-items/{cargoItem}', [CargoItemController::class, 'update']);
        Route::delete('cargo-items/{cargoItem}', [CargoItemController::class, 'destroy']);
        Route::patch('cargo-items/{cargoItem}/dates', [CargoItemDateController::class, 'update']);

        // Lookup and reports
        Route::get('lookup', LookupController::class);
        Route::get('reports/upcoming-deliveries', [ReportController::class, 'upcomingDeliveries']);
        Route::get('reports/status-overview', [ReportController::class, 'statusOverview']);
        Route::get('reports/delayed', [ReportController::class, 'delayed']);

        // Suppli reference data
        Route::get('customers', [CustomerController::class, 'index']);
        Route::put('customers', [CustomerController::class, 'upsert']);
        Route::get('products', [ProductController::class, 'index']);
        Route::put('products', [ProductController::class, 'upsert']);

        // Excel import
        Route::get('imports', [ImportController::class, 'index']);
        Route::post('imports', [ImportController::class, 'store']);
        Route::get('imports/{importRun}', [ImportController::class, 'show']);

        // Outbound notifications
        Route::get('webhooks', [WebhookEndpointController::class, 'index']);
        Route::post('webhooks', [WebhookEndpointController::class, 'store']);
        Route::delete('webhooks/{webhookEndpoint}', [WebhookEndpointController::class, 'destroy']);
        Route::get('webhooks/{webhookEndpoint}/deliveries', [WebhookEndpointController::class, 'deliveries']);
    });
});
