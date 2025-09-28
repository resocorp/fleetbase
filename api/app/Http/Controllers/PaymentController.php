<?php

namespace App\Http\Controllers;

use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    protected $paymentGateway;

    public function __construct(PaymentGatewayService $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }

    /**
     * Get available payment gateways
     */
    public function getGateways(): JsonResponse
    {
        return response()->json([
            'default_gateway' => $this->paymentGateway->getDefaultGateway(),
            'enabled_gateways' => $this->paymentGateway->getEnabledGateways(),
        ]);
    }

    /**
     * Get recommended gateway for country/currency
     */
    public function getRecommendedGateway(Request $request): JsonResponse
    {
        $country = $request->input('country');
        $currency = $request->input('currency');

        $recommendedGateway = null;

        if ($country) {
            $recommendedGateway = $this->paymentGateway->getGatewayForCountry($country);
        } elseif ($currency) {
            $recommendedGateway = $this->paymentGateway->getGatewayForCurrency($currency);
        }

        return response()->json([
            'recommended_gateway' => $recommendedGateway ?? $this->paymentGateway->getDefaultGateway(),
        ]);
    }

    /**
     * Initialize a payment
     */
    public function initializePayment(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'required|string|size:3',
                'gateway' => 'nullable|string|in:stripe,paystack',
            ]);

            $gateway = $request->input('gateway') ?? 
                      $this->paymentGateway->getGatewayForCurrency($request->input('currency'));

            $paymentData = [
                'email' => $request->input('email'),
                'amount' => $request->input('amount'),
                'currency' => $request->input('currency'),
                'reference' => $request->input('reference') ?? Str::random(16),
                'callback_url' => $request->input('callback_url'),
            ];

            $result = $this->paymentGateway->initializePayment($paymentData, $gateway);

            return response()->json([
                'success' => true,
                'gateway' => $gateway,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment initialization error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize payment',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Verify a payment
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'reference' => 'required|string',
                'gateway' => 'required|string|in:stripe,paystack',
            ]);

            $reference = $request->input('reference');
            $gateway = $request->input('gateway');

            $result = $this->paymentGateway->verifyPayment($reference, $gateway);

            return response()->json([
                'success' => true,
                'gateway' => $gateway,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment verification error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify payment',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Create a customer
     */
    public function createCustomer(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'first_name' => 'nullable|string',
                'last_name' => 'nullable|string',
                'phone' => 'nullable|string',
                'gateway' => 'nullable|string|in:stripe,paystack',
            ]);

            $gateway = $request->input('gateway') ?? $this->paymentGateway->getDefaultGateway();

            $customerData = [
                'email' => $request->input('email'),
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'phone' => $request->input('phone'),
            ];

            $result = $this->paymentGateway->createCustomer($customerData, $gateway);

            return response()->json([
                'success' => true,
                'gateway' => $gateway,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('Customer creation error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Test gateway connection
     */
    public function testGateway(Request $request, string $gateway): JsonResponse
    {
        try {
            if (!$this->paymentGateway->isGatewayEnabled($gateway)) {
                return response()->json([
                    'success' => false,
                    'message' => "Gateway '{$gateway}' is not enabled",
                ], 400);
            }

            $gatewayInstance = $this->paymentGateway->gateway($gateway);

            // Perform a simple test based on gateway type
            switch ($gateway) {
                case 'stripe':
                    // Test Stripe connection by retrieving account
                    \Stripe\Account::retrieve();
                    break;
                case 'paystack':
                    // Test Paystack connection by listing transactions
                    $gatewayInstance->listTransactions(1, 1);
                    break;
            }

            return response()->json([
                'success' => true,
                'message' => "Gateway '{$gateway}' is working correctly",
                'gateway' => $gateway,
            ]);

        } catch (\Exception $e) {
            Log::error("Gateway test error for {$gateway}: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => "Gateway '{$gateway}' test failed",
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}