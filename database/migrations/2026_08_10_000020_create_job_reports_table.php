<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('reason', [
                'suspicious_job',
                'scam_suspected',
                'company_information_wrong',
                'job_no_longer_exists',
                'misleading_salary',
                'personal_information_request',
                'other',
            ]);
            $table->text('description')->nullable();
            $table->enum('status', ['reported', 'reviewing', 'resolved', 'rejected'])->default('reported')->index();
            $table->text('admin_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['job_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_reports');
    }
};
