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
        Schema::table('crm_mailers', function (Blueprint $table) {
            $table->index(['status', 'frequency', 'next_scheduled_for'], 'status_frequency_next_scheduled_for');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_mailers', function (Blueprint $table) {
            $table->dropIndex('status_frequency_next_scheduled_for');
        });
    }
};
