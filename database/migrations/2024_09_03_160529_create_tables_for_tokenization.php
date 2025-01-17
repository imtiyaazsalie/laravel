<?php

use App\Enums\FinancePaymentTokenType;
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
        Schema::create('finance_payment_tokens', function (Blueprint $table) {
            $table->id();
            $table->integer('location_id')->nullable()->index();
            $table->integer('user_id')->nullable()->index();
            $table->integer('payment_gateway_id')->index();
            $table->string('customer_id')->nullable();
            $table->string('setup_intent_id')->nullable();
            $table->string('payment_method');
            $table->string('token');
            $table->boolean('default')->default(false);
            $table->enum('type', FinancePaymentTokenType::values());
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('user_id')->on('users');
            $table->foreign('location_id')->references('box_facility_id')->on('box_facility');
            $table->foreign('payment_gateway_id')->references('payment_gateway_id')->on('payment_gateways');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_payment_tokens');
    }
};
