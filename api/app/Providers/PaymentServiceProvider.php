<?php

namespace App\Providers;

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebhookController;
use App\Services\PaymentGatewayService;
use App\Services\PaystackService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Paystack service
        $this->app->singleton(PaystackService::class, function ($app) {
            return new PaystackService();
        });

        // Register Payment Gateway service
        $this->app->singleton(PaymentGatewayService::class, function ($app) {
            return new PaymentGatewayService($app->make(PaystackService::class));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerRoutes();
    }

    /**
     * Register payment routes
     */
    protected function registerRoutes(): void
    {
        Route::prefix('int/v1')
            ->middleware(['api'])
            ->group(function () {
                // Payment gateway routes
                Route::prefix('payments')->group(function () {
                    Route::get('gateways', [PaymentController::class, 'getGateways']);
                    Route::get('recommended-gateway', [PaymentController::class, 'getRecommendedGateway']);
                    Route::post('initialize', [PaymentController::class, 'initializePayment']);
                    Route::post('verify', [PaymentController::class, 'verifyPayment']);
                    Route::post('customers', [PaymentController::class, 'createCustomer']);
                    Route::get('test/{gateway}', [PaymentController::class, 'testGateway']);
                });

                // Webhook routes
                Route::prefix('webhooks')->group(function () {
                    Route::post('stripe', [WebhookController::class, 'handleStripeWebhook']);
                    Route::post('paystack', [WebhookController::class, 'handlePaystackWebhook']);
                });
            });
    }
}