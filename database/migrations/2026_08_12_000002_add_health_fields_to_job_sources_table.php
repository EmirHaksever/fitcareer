<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_sources', function (Blueprint $table): void {
            $table->timestamp('last_success_at')->nullable()->after('last_run_at');
            $table->timestamp('last_failure_at')->nullable()->after('last_success_at');
            $table->text('last_error')->nullable()->after('last_failure_at');
            $table->unsignedInteger('consecutive_failures')->default(0)->after('last_error');
            $table->unsignedInteger('last_items_found')->nullable()->after('consecutive_failures');
            $table->unsignedInteger('last_items_created')->nullable()->after('last_items_found');
            $table->unsignedInteger('last_items_updated')->nullable()->after('last_items_created');
        });
    }

    public function down(): void
    {
        Schema::table('job_sources', function (Blueprint $table): void {
            $table->dropColumn([
                'last_success_at',
                'last_failure_at',
                'last_error',
                'consecutive_failures',
                'last_items_found',
                'last_items_created',
                'last_items_updated',
            ]);
        });
    }
};
