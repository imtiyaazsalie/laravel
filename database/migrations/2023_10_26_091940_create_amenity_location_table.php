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
        Schema::create('amenity_location', function (Blueprint $table) {

            $table->id();
            $table->foreignId('amenity_id')->constrained('amenities');
            $table->integer('location_id');
            $table->foreign('location_id')
                ->references('box_facility_id')
                ->on('box_facility');
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amenity_location');
    }
};
