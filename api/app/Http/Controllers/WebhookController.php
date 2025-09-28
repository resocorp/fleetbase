<?php

namespace App\Http\Controllers;

use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * Handle Paystack webhooks
     */
    public function handlePaystackWebhook(Request $request): Response
    {
        try {
            $signature = $request->header('X-Paystack-Signature');
            $payload = $request->getContent();

            // Verify webhook signature
            if (!$this->paystackService->verifyWebhookSignature($payload, $signature)) {
                Log::warning('Invalid Paystack webhook signature');
                return response('Invalid signature', 400);
            }

            $event = json_decode($payload, true);

            if (!$event || !isset($event['event'])) {
                Log::warning('Invalid Paystack webhook payload');
                return response('Invalid payload', 400);
            }

            Log::info('Paystack webhook received', ['event' => $event['event']]);

            // Handle different event types
            switch ($event['event']) {
                case 'charge.success':
                    $this->handleSuccessfulPayment($event['data']);
                    break;

                case 'charge.failed':
                    $this->handleFailedPayment($event['data']);
                    break;

                case 'subscription.create':
                    $this->handleSubscriptionCreated($event['data']);
                    break;

                case 'subscription.disable':
                    $this->handleSubscriptionDisabled($event['data']);
                    break;

                case 'subscription.enable':
                    $this->handleSubscriptionEnabled($event['data']);
                    break;

                case 'invoice.create':
                    $this->handleInvoiceCreated($event['data']);
                    break;

                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($event['data']);
                    break;

                default:
                    Log::info('Unhandled Paystack webhook event', ['event' => $event['event']]);
                    break;
            }

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Paystack webhook error: ' . $e->getMessage());
            return response('Webhook processing failed', 500);
        }
    }

    /**
     * Handle Stripe webhooks (existing functionality)
     */
    public function handleStripeWebhook(Request $request): Response
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('Stripe-Signature');
            $webhookSecret = config('services.stripe.webhook_secret');

            // Verify webhook signature
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $webhookSecret);

            Log::info('Stripe webhook received', ['event' => $event['type']]);

            // Handle different event types
            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $this->handleStripePaymentSucceeded($event['data']['object']);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handleStripePaymentFailed($event['data']['object']);
                    break;

                case 'customer.subscription.created':
                    $this->handleStripeSubscriptionCreated($event['data']['object']);
                    break;

                case 'customer.subscription.updated':
                    $this->handleStripeSubscriptionUpdated($event['data']['object']);
                    break;

                case 'customer.subscription.deleted':
                    $this->handleStripeSubscriptionDeleted($event['data']['object']);
                    break;

                case 'invoice.payment_succeeded':
                    $this->handleStripeInvoicePaymentSucceeded($event['data']['object']);
                    break;

                case 'invoice.payment_failed':
                    $this->handleStripeInvoicePaymentFailed($event['data']['object']);
                    break;

                default:
                    Log::info('Unhandled Stripe webhook event', ['event' => $event['type']]);
                    break;
            }

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error('Stripe webhook error: ' . $e->getMessage());
            return response('Webhook processing failed', 500);
        }
    }

    /**
     * Handle successful Paystack payment
     */
    protected function handleSuccessfulPayment($data)
    {
        Log::info('Paystack payment successful', ['reference' => $data['reference']]);
        
        // Update extension purchase record
        $this->updateExtensionPurchase($data['reference'], 'completed', 'paystack', $data);
        
        // Additional business logic here
    }

    /**
     * Handle failed Paystack payment
     */
    protected function handleFailedPayment($data)
    {
        Log::info('Paystack payment failed', ['reference' => $data['reference']]);
        
        // Update extension purchase record
        $this->updateExtensionPurchase($data['reference'], 'failed', 'paystack', $data);
    }

    /**
     * Handle Paystack subscription created
     */
    protected function handleSubscriptionCreated($data)
    {
        Log::info('Paystack subscription created', ['subscription_code' => $data['subscription_code']]);
        
        // Handle subscription creation logic
    }

    /**
     * Handle Paystack subscription disabled
     */
    protected function handleSubscriptionDisabled($data)
    {
        Log::info('Paystack subscription disabled', ['subscription_code' => $data['subscription_code']]);
        
        // Handle subscription cancellation logic
    }

    /**
     * Handle Paystack subscription enabled
     */
    protected function handleSubscriptionEnabled($data)
    {
        Log::info('Paystack subscription enabled', ['subscription_code' => $data['subscription_code']]);
        
        // Handle subscription reactivation logic
    }

    /**
     * Handle Paystack invoice created
     */
    protected function handleInvoiceCreated($data)
    {
        Log::info('Paystack invoice created', ['invoice_code' => $data['invoice_code']]);
    }

    /**
     * Handle Paystack invoice payment failed
     */
    protected function handleInvoicePaymentFailed($data)
    {
        Log::info('Paystack invoice payment failed', ['invoice_code' => $data['invoice_code']]);
    }

    /**
     * Handle Stripe payment succeeded
     */
    protected function handleStripePaymentSucceeded($paymentIntent)
    {
        Log::info('Stripe payment succeeded', ['payment_intent' => $paymentIntent['id']]);
        
        // Update extension purchase record
        $this->updateExtensionPurchase($paymentIntent['id'], 'completed', 'stripe', $paymentIntent);
    }

    /**
     * Handle Stripe payment failed
     */
    protected function handleStripePaymentFailed($paymentIntent)
    {
        Log::info('Stripe payment failed', ['payment_intent' => $paymentIntent['id']]);
        
        // Update extension purchase record
        $this->updateExtensionPurchase($paymentIntent['id'], 'failed', 'stripe', $paymentIntent);
    }

    /**
     * Handle Stripe subscription created
     */
    protected function handleStripeSubscriptionCreated($subscription)
    {
        Log::info('Stripe subscription created', ['subscription' => $subscription['id']]);
    }

    /**
     * Handle Stripe subscription updated
     */
    protected function handleStripeSubscriptionUpdated($subscription)
    {
        Log::info('Stripe subscription updated', ['subscription' => $subscription['id']]);
    }

    /**
     * Handle Stripe subscription deleted
     */
    protected function handleStripeSubscriptionDeleted($subscription)
    {
        Log::info('Stripe subscription deleted', ['subscription' => $subscription['id']]);
    }

    /**
     * Handle Stripe invoice payment succeeded
     */
    protected function handleStripeInvoicePaymentSucceeded($invoice)
    {
        Log::info('Stripe invoice payment succeeded', ['invoice' => $invoice['id']]);
    }

    /**
     * Handle Stripe invoice payment failed
     */
    protected function handleStripeInvoicePaymentFailed($invoice)
    {
        Log::info('Stripe invoice payment failed', ['invoice' => $invoice['id']]);
    }

    /**
     * Update extension purchase record
     */
    protected function updateExtensionPurchase($reference, $status, $gateway, $data)
    {
        // This would typically update the registry_extension_purchases table
        // Implementation depends on your specific model structure
        
        Log::info('Updating extension purchase', [
            'reference' => $reference,
            'status' => $status,
            'gateway' => $gateway,
        ]);
        
        // Example implementation:
        // ExtensionPurchase::where('reference', $reference)
        //     ->update([
        //         'status' => $status,
        //         'payment_gateway' => $gateway,
        //         'payment_data' => json_encode($data),
        //     ]);
    }
}