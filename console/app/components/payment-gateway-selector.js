import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { tracked } from '@glimmer/tracking';
import { action } from '@ember/object';
import { task } from 'ember-concurrency';

export default class PaymentGatewaySelectorComponent extends Component {
    @service paymentGateway;
    @service notifications;

    @tracked selectedGateway = null;
    @tracked recommendedGateway = null;
    @tracked isLoading = false;

    constructor() {
        super(...arguments);
        this.selectedGateway = this.args.selectedGateway || this.paymentGateway.defaultGateway;
        this.loadRecommendedGateway();
    }

    get enabledGateways() {
        return this.paymentGateway.enabledGateways.map(gateway => ({
            id: gateway,
            ...this.paymentGateway.getGatewayConfig(gateway)
        }));
    }

    get isPaystackRecommended() {
        return this.recommendedGateway === 'paystack';
    }

    get isStripeRecommended() {
        return this.recommendedGateway === 'stripe';
    }

    @task *loadRecommendedGateway() {
        if (this.args.country || this.args.currency) {
            try {
                const recommended = yield this.paymentGateway.getRecommendedGateway.perform({
                    country: this.args.country,
                    currency: this.args.currency
                });
                this.recommendedGateway = recommended;
                
                // Auto-select recommended gateway if no gateway is pre-selected
                if (!this.args.selectedGateway && recommended) {
                    this.selectedGateway = recommended;
                    this.notifySelection();
                }
            } catch (error) {
                console.error('Failed to load recommended gateway:', error);
            }
        }
    }

    @action
    selectGateway(gateway) {
        this.selectedGateway = gateway;
        this.notifySelection();
    }

    @action
    notifySelection() {
        if (this.args.onGatewaySelected) {
            this.args.onGatewaySelected(this.selectedGateway);
        }
    }

    @task *testGateway(gateway) {
        yield this.paymentGateway.testGateway.perform(gateway);
    }
}