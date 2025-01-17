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
        Schema::create('operating_hours', function (Blueprint $table) {
            $table->id();

            $table->tinyInteger('day');

            $table->string('opening_time');
            $table->string('closing_time');

            //TODO: Investigate why FK is not created.
            $table->bigInteger('location_id')
                ->foreign('location_id')
                ->references('box_facility_id')
                ->on('box_facility');

            $table->unique(['location_id', 'day', 'opening_time']);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operating_hours');
    }
};
