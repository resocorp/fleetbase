<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected $secretKey;
    protected $publicKey;
    protected $baseUrl;
    protected $webhookSecret;

    public function __construct()
    {
        $this->secretKey = Config::get('services.paystack.secret_key');
        $this->publicKey = Config::get('services.paystack.public_key');
        $this->baseUrl = Config::get('services.paystack.base_url');
        $this->webhookSecret = Config::get('services.paystack.webhook_secret');
    }

    /**
     * Create a customer on Paystack
     */
    public function createCustomer($email, $firstName = null, $lastName = null, $phone = null)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/customer', [
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to create customer: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Paystack create customer error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Initialize a transaction
     */
    public function initializeTransaction($email, $amount, $currency = 'NGN', $reference = null, $callbackUrl = null)
    {
        try {
            $data = [
                'email' => $email,
                'amount' => $amount * 100, // Convert to kobo/pesewas
                'currency' => $currency,
            ];

            if ($reference) {
                $data['reference'] = $reference;
            }

            if ($callbackUrl) {
                $data['callback_url'] = $callbackUrl;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/transaction/initialize', $data);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to initialize transaction: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Paystack initialize transaction error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verify a transaction
     */
    public function verifyTransaction($reference)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get($this->baseUrl . '/transaction/verify/' . $reference);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to verify transaction: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Paystack verify transaction error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a subscription plan
     */
    public function createPlan($name, $amount, $interval, $currency = 'NGN')
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/plan', [
                'name' => $name,
                'amount' => $amount * 100, // Convert to kobo/pesewas
                'interval' => $interval, // daily, weekly, monthly, annually
                'currency' => $currency,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to create plan: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Paystack create plan error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a subscription
     */
    public function createSubscription($customerCode, $planCode, $authorization = null)
    {
        try {
            $data = [
                'customer' => $customerCode,
                'plan' => $planCode,
            ];

            if ($authorization) {
                $data['authorization'] = $authorization;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/subscription', $data);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to create subscription: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Paystack create subscription error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get customer details
     */
    public function getCustomer($customerCode)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get($this->baseUrl . '/customer/' . $customerCode);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to get customer: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Paystack get customer error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * List transactions
     */
    public function listTransactions($perPage = 50, $page = 1, $customer = null)
    {
        try {
            $params = [
                'perPage' => $perPage,
                'page' => $page,
            ];

            if ($customer) {
                $params['customer'] = $customer;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
            ])->get($this->baseUrl . '/transaction', $params);

            if ($response->successful()) {
                return $response->json();
            }

            throw new Exception('Failed to list transactions: ' . $response->body());
        } catch (Exception $e) {
            Log::error('Paystack list transactions error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature($payload, $signature)
    {
        if (!$this->webhookSecret) {
            return false;
        }

        $computedSignature = hash_hmac('sha512', $payload, $this->webhookSecret);
        return hash_equals($signature, $computedSignature);
    }

    /**
     * Get public key for frontend
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }
}