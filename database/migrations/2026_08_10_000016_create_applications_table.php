<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->string('resume_snapshot_path')->nullable();
            $table->text('cover_letter')->nullable();
            $table->enum('status', ['submitted', 'under_review', 'shortlisted', 'interview', 'offered', 'rejected', 'withdrawn'])->default('submitted')->index();
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->unsignedTinyInteger('trust_score')->nullable();
            $table->timestamp('applied_at');
            $table->timestamp('status_updated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['candidate_profile_id', 'job_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
