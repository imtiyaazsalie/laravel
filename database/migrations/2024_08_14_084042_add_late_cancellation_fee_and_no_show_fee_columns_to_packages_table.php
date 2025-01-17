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
        Schema::table('packages', function (Blueprint $table) {
            $table->float('late_cancellation_fee')
                ->default(0)
                ->after('package_price')
                ->index();

            $table->float('no_show_fee')
                ->default(0)
                ->after('late_cancellation_fee')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['late_cancellation_fee', 'no_show_fee']);
        });
    }
};
