<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_analyses', function (Blueprint $table): void {
            $table->id();
            $table->enum('type', ['job_trust', 'cv_job_fit'])->index();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->nullable();
            $table->string('label')->nullable();
            $table->text('summary')->nullable();
            $table->json('details')->nullable();
            $table->string('ai_model')->nullable();
            $table->string('analysis_version')->nullable();
            $table->string('prompt_version')->nullable();
            $table->json('raw_response')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->boolean('is_latest')->default(false)->index();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->index(['job_id', 'candidate_profile_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};
