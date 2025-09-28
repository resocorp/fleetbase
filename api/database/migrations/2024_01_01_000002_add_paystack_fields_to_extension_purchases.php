<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('registry_extension_purchases', function (Blueprint $table) {
            $table->string('paystack_reference')->nullable()->after('stripe_payment_intent_id');
            $table->string('paystack_transaction_id')->nullable()->after('paystack_reference');
            $table->string('payment_gateway')->default('stripe')->after('paystack_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registry_extension_purchases', function (Blueprint $table) {
            $table->dropColumn(['paystack_reference', 'paystack_transaction_id', 'payment_gateway']);
        });
    }
};