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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->integer('location_id')->unique();

            $table->foreign('location_id')
                ->references('box_facility_id')
                ->on('box_facility');

            $table->string('street_number');
            $table->string('city');
            $table->string('state_province_region');
            $table->string('postal_code');
            $table->string('country_code', 3);

            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['location_id', 'street_number']);
            $table->index(['location_id', 'city']);
            $table->index(['location_id', 'state_province_region']);
            $table->index(['location_id', 'postal_code']);
            $table->index(['location_id', 'country_code']);
            $table->index(['location_id', 'latitude']);
            $table->index(['location_id', 'longitude']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
