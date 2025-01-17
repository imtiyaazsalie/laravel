<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('crm_mailers', function (Blueprint $table) {
            $table->timestamp('next_scheduled_for')->nullable();
            $table->string('frequency')->nullable();
            $table->json('repeat_on')->nullable();
            $table->string('time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('crm_mailers', function (Blueprint $table) {
            $table->dropColumn('next_scheduled_for');
            $table->dropColumn('frequency');
            $table->dropColumn('repeat_on');
            $table->dropColumn('time');
        });
    }
};
