<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Config;
use Laravel\Cashier\Cashier;

class PaymentGatewayService
{
    protected $defaultGateway;
    protected $enabledGateways;
    protected $gateways = [];

    public function __construct(PaystackService $paystackService)
    {
        $this->defaultGateway = Config::get('payment.default_gateway', 'stripe');
        $this->enabledGateways = Config::get('payment.enabled_gateways', ['stripe']);
        
        $this->gateways = [
            'stripe' => new StripeService(),
            'paystack' => $paystackService,
        ];
    }

    /**
     * Get a specific gateway instance
     */
    public function gateway($name = null)
    {
        $gateway = $name ?? $this->defaultGateway;
        
        if (!in_array($gateway, $this->enabledGateways)) {
            throw new Exception("Payment gateway '{$gateway}' is not enabled");
        }

        if (!isset($this->gateways[$gateway])) {
            throw new Exception("Payment gateway '{$gateway}' is not supported");
        }

        return $this->gateways[$gateway];
    }

    /**
     * Get the best gateway for a country
     */
    public function getGatewayForCountry($countryCode)
    {
        $regionalGateways = Config::get('payment.regional_gateways', []);
        
        if (isset($regionalGateways[$countryCode])) {
            $preferredGateway = $regionalGateways[$countryCode];
            
            if (in_array($preferredGateway, $this->enabledGateways)) {
                return $preferredGateway;
            }
        }

        return $this->defaultGateway;
    }

    /**
     * Get the best gateway for a currency
     */
    public function getGatewayForCurrency($currency)
    {
        $currencyGateways = Config::get('payment.currency_gateways', []);
        
        if (isset($currencyGateways[$currency])) {
            $preferredGateway = $currencyGateways[$currency];
            
            if (in_array($preferredGateway, $this->enabledGateways)) {
                return $preferredGateway;
            }
        }

        return $this->defaultGateway;
    }

    /**
     * Create a customer across gateways
     */
    public function createCustomer($data, $gateway = null)
    {
        $gatewayName = $gateway ?? $this->defaultGateway;
        $gatewayInstance = $this->gateway($gatewayName);

        switch ($gatewayName) {
            case 'stripe':
                return $gatewayInstance->createCustomer($data);
            case 'paystack':
                return $gatewayInstance->createCustomer(
                    $data['email'],
                    $data['first_name'] ?? null,
                    $data['last_name'] ?? null,
                    $data['phone'] ?? null
                );
            default:
                throw new Exception("Unsupported gateway: {$gatewayName}");
        }
    }

    /**
     * Initialize a payment across gateways
     */
    public function initializePayment($data, $gateway = null)
    {
        $gatewayName = $gateway ?? $this->defaultGateway;
        $gatewayInstance = $this->gateway($gatewayName);

        switch ($gatewayName) {
            case 'stripe':
                return $gatewayInstance->createPaymentIntent($data);
            case 'paystack':
                return $gatewayInstance->initializeTransaction(
                    $data['email'],
                    $data['amount'],
                    $data['currency'] ?? 'NGN',
                    $data['reference'] ?? null,
                    $data['callback_url'] ?? null
                );
            default:
                throw new Exception("Unsupported gateway: {$gatewayName}");
        }
    }

    /**
     * Verify a payment across gateways
     */
    public function verifyPayment($reference, $gateway = null)
    {
        $gatewayName = $gateway ?? $this->defaultGateway;
        $gatewayInstance = $this->gateway($gatewayName);

        switch ($gatewayName) {
            case 'stripe':
                return $gatewayInstance->retrievePaymentIntent($reference);
            case 'paystack':
                return $gatewayInstance->verifyTransaction($reference);
            default:
                throw new Exception("Unsupported gateway: {$gatewayName}");
        }
    }

    /**
     * Create a subscription plan across gateways
     */
    public function createPlan($data, $gateway = null)
    {
        $gatewayName = $gateway ?? $this->defaultGateway;
        $gatewayInstance = $this->gateway($gatewayName);

        switch ($gatewayName) {
            case 'stripe':
                return $gatewayInstance->createProduct($data);
            case 'paystack':
                return $gatewayInstance->createPlan(
                    $data['name'],
                    $data['amount'],
                    $data['interval'],
                    $data['currency'] ?? 'NGN'
                );
            default:
                throw new Exception("Unsupported gateway: {$gatewayName}");
        }
    }

    /**
     * Get enabled gateways
     */
    public function getEnabledGateways()
    {
        return $this->enabledGateways;
    }

    /**
     * Get default gateway
     */
    public function getDefaultGateway()
    {
        return $this->defaultGateway;
    }

    /**
     * Check if a gateway is enabled
     */
    public function isGatewayEnabled($gateway)
    {
        return in_array($gateway, $this->enabledGateways);
    }
}

/**
 * Stripe service wrapper for consistency
 */
class StripeService
{
    public function createCustomer($data)
    {
        return \Stripe\Customer::create($data);
    }

    public function createPaymentIntent($data)
    {
        return \Stripe\PaymentIntent::create($data);
    }

    public function retrievePaymentIntent($id)
    {
        return \Stripe\PaymentIntent::retrieve($id);
    }

    public function createProduct($data)
    {
        return \Stripe\Product::create($data);
    }
}