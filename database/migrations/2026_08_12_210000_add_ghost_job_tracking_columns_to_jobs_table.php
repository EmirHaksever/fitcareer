<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table): void {
            $table->timestamp('first_seen_at')->nullable()->after('last_seen_at');
            $table->timestamp('provider_updated_at')->nullable()->after('first_seen_at');

            $table->index('first_seen_at');
            $table->index('provider_updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table): void {
            $table->dropIndex(['first_seen_at']);
            $table->dropIndex(['provider_updated_at']);
            $table->dropColumn(['first_seen_at', 'provider_updated_at']);
        });
    }
};
