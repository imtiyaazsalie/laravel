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
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropColumn('location_id');
            $table->dropColumn('street_address');
            $table->dropColumn('city');
            $table->dropColumn('state_province_region');
            $table->dropColumn('postal_code');
            $table->dropColumn('country_code');
            $table->dropColumn('latitude');
            $table->dropColumn('longitude');
            $table->dropTimestamps();
            $table->dropSoftDeletes();
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->string('full_address')->after('id');
            $table->json('coordinates')->nullable()->after('full_address');
            $table->json('structured_address')->nullable()->after('coordinates');
            $table->morphs('addressable');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropMorphs('addressable');
            $table->dropColumn('full_address');
            $table->dropColumn('coordinates');
            $table->dropColumn('structured_address');
        });
    }
};
