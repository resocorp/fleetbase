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
        Schema::table('registry_extensions', function (Blueprint $table) {
            $table->string('paystack_plan_code')->nullable()->after('stripe_product_id');
            $table->json('supported_gateways')->nullable()->after('paystack_plan_code');
            $table->json('gateway_prices')->nullable()->after('supported_gateways');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registry_extensions', function (Blueprint $table) {
            $table->dropColumn(['paystack_plan_code', 'supported_gateways', 'gateway_prices']);
        });
    }
};