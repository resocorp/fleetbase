import Service from '@ember/service';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { task } from 'ember-concurrency';

export default class PaymentGatewayService extends Service {
    @service config;
    @service fetch;
    @service notifications;

    @tracked enabledGateways = [];
    @tracked defaultGateway = 'stripe';

    constructor() {
        super(...arguments);
        this.loadConfiguration();
    }

    loadConfiguration() {
        this.defaultGateway = this.config.get('payment.defaultGateway') || 'stripe';
        this.enabledGateways = this.config.get('payment.enabledGateways') || ['stripe'];
    }

    get isPaystackEnabled() {
        return this.enabledGateways.includes('paystack');
    }

    get isStripeEnabled() {
        return this.enabledGateways.includes('stripe');
    }

    /**
     * Get recommended gateway for country/currency
     */
    @task *getRecommendedGateway(options = {}) {
        try {
            const response = yield this.fetch.get('payments/recommended-gateway', options);
            return response.recommended_gateway;
        } catch (error) {
            console.error('Failed to get recommended gateway:', error);
            return this.defaultGateway;
        }
    }

    /**
     * Initialize payment with selected gateway
     */
    @task *initializePayment(paymentData, gateway = null) {
        const selectedGateway = gateway || this.defaultGateway;

        try {
            switch (selectedGateway) {
                case 'stripe':
                    return yield this.initializeStripePayment(paymentData);
                case 'paystack':
                    return yield this.initializePaystackPayment(paymentData);
                default:
                    throw new Error(`Unsupported payment gateway: ${selectedGateway}`);
            }
        } catch (error) {
            this.notifications.error(`Payment initialization failed: ${error.message}`);
            throw error;
        }
    }

    /**
     * Initialize Stripe payment
     */
    async initializeStripePayment(paymentData) {
        // Load Stripe if not already loaded
        if (!window.Stripe) {
            await this.loadStripe();
        }

        const stripe = window.Stripe(this.config.get('stripe.publishableKey'));

        // Create payment intent via API
        const response = await this.fetch.post('payments/initialize', {
            ...paymentData,
            gateway: 'stripe'
        });

        if (response.success) {
            return {
                gateway: 'stripe',
                stripe,
                clientSecret: response.data.client_secret,
                paymentIntent: response.data
            };
        }

        throw new Error(response.message || 'Failed to initialize Stripe payment');
    }

    /**
     * Initialize Paystack payment
     */
    async initializePaystackPayment(paymentData) {
        // Load Paystack if not already loaded
        if (!window.PaystackPop) {
            await this.loadPaystack();
        }

        // Initialize transaction via API
        const response = await this.fetch.post('payments/initialize', {
            ...paymentData,
            gateway: 'paystack'
        });

        if (response.success) {
            return new Promise((resolve, reject) => {
                const popup = window.PaystackPop.setup({
                    key: this.config.get('paystack.publicKey'),
                    email: paymentData.email,
                    amount: paymentData.amount * 100, // Convert to kobo
                    currency: paymentData.currency || 'NGN',
                    ref: response.data.data.reference,
                    callback: (response) => {
                        resolve({
                            gateway: 'paystack',
                            reference: response.reference,
                            status: response.status,
                            transaction: response.transaction
                        });
                    },
                    onClose: () => {
                        reject(new Error('Payment cancelled by user'));
                    }
                });

                popup.openIframe();
            });
        }

        throw new Error(response.message || 'Failed to initialize Paystack payment');
    }

    /**
     * Verify payment
     */
    @task *verifyPayment(reference, gateway) {
        try {
            const response = yield this.fetch.post('payments/verify', {
                reference,
                gateway
            });

            if (response.success) {
                return response.data;
            }

            throw new Error(response.message || 'Payment verification failed');
        } catch (error) {
            this.notifications.error(`Payment verification failed: ${error.message}`);
            throw error;
        }
    }

    /**
     * Load Stripe SDK
     */
    async loadStripe() {
        return new Promise((resolve, reject) => {
            if (window.Stripe) {
                resolve(window.Stripe);
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://js.stripe.com/v3/';
            script.onload = () => resolve(window.Stripe);
            script.onerror = () => reject(new Error('Failed to load Stripe SDK'));
            document.head.appendChild(script);
        });
    }

    /**
     * Load Paystack SDK
     */
    async loadPaystack() {
        return new Promise((resolve, reject) => {
            if (window.PaystackPop) {
                resolve(window.PaystackPop);
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://js.paystack.co/v1/inline.js';
            script.onload = () => resolve(window.PaystackPop);
            script.onerror = () => reject(new Error('Failed to load Paystack SDK'));
            document.head.appendChild(script);
        });
    }

    /**
     * Get gateway configuration for display
     */
    getGatewayConfig(gateway) {
        const configs = {
            stripe: {
                name: 'Stripe',
                logo: '/assets/images/stripe-logo.png',
                description: 'Secure payments with Stripe',
                currencies: ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
                countries: ['US', 'CA', 'GB', 'DE', 'FR', 'AU']
            },
            paystack: {
                name: 'Paystack',
                logo: '/assets/images/paystack-logo.png',
                description: 'African payments with Paystack',
                currencies: ['NGN', 'GHS', 'ZAR', 'KES'],
                countries: ['NG', 'GH', 'ZA', 'KE']
            }
        };

        return configs[gateway] || null;
    }

    /**
     * Test gateway connection
     */
    @task *testGateway(gateway) {
        try {
            const response = yield this.fetch.get(`payments/test/${gateway}`);
            
            if (response.success) {
                this.notifications.success(`${gateway} gateway is working correctly`);
                return true;
            }

            this.notifications.error(`${gateway} gateway test failed: ${response.message}`);
            return false;
        } catch (error) {
            this.notifications.error(`${gateway} gateway test failed: ${error.message}`);
            return false;
        }
    }
}