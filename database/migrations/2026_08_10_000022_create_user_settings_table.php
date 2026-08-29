<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('notification_email_enabled')->default(true);
            $table->boolean('notification_push_enabled')->default(true);
            $table->boolean('notification_sms_enabled')->default(false);
            $table->boolean('notify_job_matches')->default(true);
            $table->boolean('notify_application_updates')->default(true);
            $table->boolean('notify_system')->default(true);
            $table->boolean('notify_promotions')->default(false);
            $table->enum('profile_visibility', ['public', 'private', 'companies_only'])->default('public');
            $table->string('language', 5)->default('tr');
            $table->string('timezone')->default('Europe/Istanbul');
            $table->enum('theme', ['light', 'dark', 'system'])->default('light');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
