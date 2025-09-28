import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class PaymentProcessorComponent extends Component {
    @service paymentGateway;
    @service notifications;

    @tracked selectedGateway = null;
    @tracked paymentResult = null;
    @tracked isProcessing = false;

    constructor() {
        super(...arguments);
        this.selectedGateway = this.args.defaultGateway || this.paymentGateway.defaultGateway;
    }

    get paymentData() {
        return {
            email: this.args.email,
            amount: this.args.amount,
            currency: this.args.currency || 'USD',
            reference: this.args.reference,
            callback_url: this.args.callbackUrl,
            ...this.args.additionalData
        };
    }

    @action
    onGatewaySelected(gateway) {
        this.selectedGateway = gateway;
        if (this.args.onGatewayChanged) {
            this.args.onGatewayChanged(gateway);
        }
    }

    @task *processPayment() {
        this.isProcessing = true;
        this.paymentResult = null;

        try {
            const result = yield this.paymentGateway.initializePayment.perform(
                this.paymentData,
                this.selectedGateway
            );

            this.paymentResult = result;

            // Handle different gateway responses
            if (this.selectedGateway === 'stripe') {
                yield this.handleStripePayment(result);
            } else if (this.selectedGateway === 'paystack') {
                yield this.handlePaystackPayment(result);
            }

        } catch (error) {
            this.notifications.error(`Payment failed: ${error.message}`);
            if (this.args.onPaymentError) {
                this.args.onPaymentError(error);
            }
        } finally {
            this.isProcessing = false;
        }
    }

    async handleStripePayment(result) {
        const { stripe, clientSecret } = result;

        // For card payments, you would typically collect card details here
        // This is a simplified example
        const { error, paymentIntent } = await stripe.confirmCardPayment(clientSecret);

        if (error) {
            throw new Error(error.message);
        }

        if (paymentIntent.status === 'succeeded') {
            this.notifications.success('Payment successful!');
            if (this.args.onPaymentSuccess) {
                this.args.onPaymentSuccess({
                    gateway: 'stripe',
                    paymentIntent,
                    reference: paymentIntent.id
                });
            }
        }
    }

    async handlePaystackPayment(result) {
        // Paystack popup handles the payment flow
        // Result is returned when payment is complete
        if (result.status === 'success') {
            // Verify payment on backend
            const verification = await this.paymentGateway.verifyPayment.perform(
                result.reference,
                'paystack'
            );

            if (verification.data.status === 'success') {
                this.notifications.success('Payment successful!');
                if (this.args.onPaymentSuccess) {
                    this.args.onPaymentSuccess({
                        gateway: 'paystack',
                        transaction: verification.data,
                        reference: result.reference
                    });
                }
            } else {
                throw new Error('Payment verification failed');
            }
        } else {
            throw new Error('Payment was not completed');
        }
    }

    @action
    retryPayment() {
        this.processPayment.perform();
    }

    @action
    cancelPayment() {
        if (this.args.onPaymentCancel) {
            this.args.onPaymentCancel();
        }
    }
}