<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table): void {
            $table->timestamp('last_seen_at')->nullable()->after('last_scraped_at')->index();
        });

        DB::statement(
            "ALTER TABLE jobs MODIFY scrape_status ENUM('pending', 'in_progress', 'success', 'failed', 'stale') NULL"
        );
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table): void {
            $table->dropColumn('last_seen_at');
        });

        DB::statement(
            "ALTER TABLE jobs MODIFY scrape_status ENUM('pending', 'in_progress', 'success', 'failed') NULL"
        );
    }
};
