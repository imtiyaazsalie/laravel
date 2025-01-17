<?php

use App\Enums\PaymentGateway;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('payment_gateways')->insert([
            'payment_gateway_id' => 10,
            'payment_gateway_name' => 'Payfast',
            'is_active' => true,
        ]);

        Schema::table('box_facility', function (Blueprint $table) {
            $table->integer('billing_payment_gateway_id')->nullable()->after('box_id')->default(PaymentGateway::NO_GATEWAY);
            $table->foreign('billing_payment_gateway_id')->references('payment_gateway_id')->on('payment_gateways');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('box_facility', function (Blueprint $table) {
            $table->dropForeign('box_facility_billing_payment_gateway_id_foreign');
            $table->dropColumn('billing_payment_gateway_id');
        });

        DB::table('payment_gateways')->where('payment_gateway_id', '=', 10)->delete();
    }
};
