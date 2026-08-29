<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('base_url')->nullable();
            $table->enum('type', ['scraper', 'api_integration'])->default('scraper');
            $table->boolean('is_active')->default(true)->index();
            $table->json('config')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_sources');
    }
};
