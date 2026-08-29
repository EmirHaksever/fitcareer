<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_source_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['running', 'completed', 'failed', 'partial'])->default('running')->index();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('items_found')->default(0);
            $table->unsignedInteger('items_created')->default(0);
            $table->unsignedInteger('items_updated')->default(0);
            $table->unsignedInteger('items_skipped')->default(0);
            $table->unsignedInteger('items_failed')->default(0);
            $table->json('error_log')->nullable();
            $table->timestamps();

            $table->index(['job_source_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_import_runs');
    }
};
