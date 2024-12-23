<?php

use Illuminate\Support\Facades\Schema;
use App\Models\EmailNotificationSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

    public function up(): void
    {
        Schema::table('email_notification_settings', function (Blueprint $table) {
            EmailNotificationSetting::where('slug', 'clock-in-notification')->delete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification', function (Blueprint $table) {
            //
        });
    }
};
