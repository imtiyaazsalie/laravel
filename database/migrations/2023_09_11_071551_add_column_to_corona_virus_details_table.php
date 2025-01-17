<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('coronavirus_vaccination_details', 'box_id')) { //check the column
            Schema::table('coronavirus_vaccination_details', function (Blueprint $table) {
                //  $table->dropForeign('FK_DCB62998D8177B3F');
            });

            Artisan::call('db:seed', [
                '--class' => 'CoronavirusVaccinationDetailsAddTenantIdSeeder',
                '--force' => true,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coronavirus_vaccination_details', function (Blueprint $table) {
            $table->integer('box_id')->nullable();
        });
    }
};
