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
        Schema::create('tenant_affiliations', function (Blueprint $table) {
            $table->id();

            $table->integer('tenant_id')->constrained('boxes', 'box_id');

            $table->tinyInteger('affiliate_id');

            $table->unique(['tenant_id', 'affiliate_id'])->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_affiliations');
    }
};
