<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->integer('min_booked_members_count')->nullable()->index()->after('cancellation_threshhold');
            $table->integer('auto_cancel_threshold_min')->nullable()->index()->after('cancellation_threshhold');
        });

        Schema::table('class_to_dates', function (Blueprint $table) {
            $table->integer('min_booked_members_count')->nullable()->index();
            $table->integer('auto_cancel_threshold_min')->nullable()->index();
        });

        DB::table('crm_notifications')->insert([
            'name' => 'Class Update: Auto-class cancellation - minimum capacity',
            'status' => 'enabled',
            'emailSubject' => 'Class cancelled due to minimum booking requirement - Octiv',
            'emailContent' => 'Dear <strong>[member_name]</strong>,<br /><br />We regret to inform you that following class has been cancelled due to there being less than the minimum number of bookings. You have been refunded your session. Please use the Octiv app to find an alternative class/session to book.<br /><br /><u>CLASS DETAILS:</u><br />Class name: [class_name]<br />Class date: [class_date]<br />Class time: [class_time]<br />',
            'system_type' => 'editable',
            'system_context' => 'class_auto_cancelled',
            'description' => 'Sent to member. Occurs when a class is canceled due to the minimum booking requirement not being met.',
            'unsubscribable' => '0',
        ]);

        DB::table('crm_notifications')->insert([
            'name' => 'Class Update: Auto-class cancellation - minimum capacity',
            'status' => 'enabled',
            'emailSubject' => 'Class cancelled due to minimum booking requirement - Octiv',
            'emailContent' => 'Dear <strong>[coach_name]</strong>,<br /><br />We regret to inform you that following class has been cancelled due to there being less than the minimum number of bookings. Your members have been informed and refunded their session.<br /><br /><u>CLASS DETAILS:</u><br />Class name: [class_name]<br />Class date: [class_date]<br />Class time: [class_time]<br />',
            'system_type' => 'editable',
            'system_context' => 'coach_class_auto_cancelled',
            'description' => 'Sent to staff. Occurs when a class is canceled due to the minimum booking requirement not being met.',
            'unsubscribable' => '0',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn(['min_booked_members_count', 'auto_cancel_threshold_min']);
        });

        Schema::table('class_to_dates', function (Blueprint $table) {
            $table->dropColumn(['min_booked_members_count', 'auto_cancel_threshold_min']);
        });
    }
};
