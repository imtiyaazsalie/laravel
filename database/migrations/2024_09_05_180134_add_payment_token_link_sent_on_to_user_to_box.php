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
        Schema::table('user_to_box', function (Blueprint $table) {
            $table->dateTime('payment_token_link_sent_on')->nullable()->after('go_cardless_link_sent_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_to_box', function (Blueprint $table) {
            $table->dropColumn('payment_token_link_sent_on');
        });
    }
};
