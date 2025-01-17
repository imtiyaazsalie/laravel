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
            $table->integer('lead_member_id')->nullable()->index();
            $table->foreign('lead_member_id', 'lead_member_id_idx')
                ->references('member_id')
                ->on('lead_members')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_to_box', function (Blueprint $table) {
            $table->dropForeign('lead_member_id_idx');
            $table->dropColumn('lead_member_id');
        });
    }
};
