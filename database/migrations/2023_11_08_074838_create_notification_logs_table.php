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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();

            $table->tinyInteger('type')->index();
            $table->tinyInteger('status')->index();

            $table->integer('tenant_id')->nullable()->constrained('boxes', 'box_id');

            $table->integer('location_id')->nullable()->constrained('box_facility', 'box_facility_id');

            $table->integer('user_id')->nullable()->constrained('users', 'user_id');

            $table->integer('notification_id')->nullable()->constrained('crm_notifications', 'notification_id');

            $table->integer('mailer_id')->nullable()->constrained('crm_mailers', 'mailer_id');

            $table->string('recipient');

            $table->string('title')->nullable();

            $table->longText('content');

            $table->string('message');

            $table->string('reply_to');

            $table->json('cc')->nullable();

            $table->json('attachment')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
