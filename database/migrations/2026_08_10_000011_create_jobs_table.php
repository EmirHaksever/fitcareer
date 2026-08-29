<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('source', ['internal', 'scraped'])->default('internal')->index();
            $table->string('source_company_name')->nullable();
            $table->string('external_url')->nullable();
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->string('slug');
            $table->longText('description');
            $table->longText('requirements')->nullable();
            $table->longText('responsibilities')->nullable();
            $table->string('category')->nullable()->index();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'internship', 'freelance']);
            $table->enum('work_type', ['remote', 'hybrid', 'onsite'])->index();
            $table->enum('experience_level', ['intern', 'entry', 'mid', 'senior', 'lead', 'executive'])->nullable();
            $table->string('city')->nullable()->index();
            $table->string('country')->nullable();
            $table->decimal('salary_min', 10, 2)->nullable();
            $table->decimal('salary_max', 10, 2)->nullable();
            $table->string('salary_currency', 3)->default('TRY');
            $table->boolean('is_salary_visible')->default(false);
            $table->date('application_deadline')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->enum('status', ['draft', 'pending_review', 'published', 'expired', 'closed', 'flagged'])->default('draft')->index();
            $table->unsignedTinyInteger('trust_score')->nullable();
            $table->enum('trust_label', ['verified', 'suspicious', 'low_trust', 'unrated'])->default('unrated')->index();
            $table->enum('trust_analysis_status', ['pending', 'analyzing', 'completed', 'failed'])->default('pending')->index();
            $table->string('content_hash', 64)->nullable()->index();
            $table->timestamp('last_scraped_at')->nullable()->index();
            $table->enum('scrape_status', ['pending', 'in_progress', 'success', 'failed'])->nullable();
            $table->text('scrape_error')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('applications_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['job_source_id', 'external_id']);
            $table->fullText(['title', 'description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
