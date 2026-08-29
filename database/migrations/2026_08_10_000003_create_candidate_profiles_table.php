<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline')->nullable();
            $table->text('summary')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('profile_photo_path')->nullable();
            $table->string('cv_file_path')->nullable();
            $table->json('cv_parsed_data')->nullable();
            $table->unsignedTinyInteger('profile_strength_score')->default(0);
            $table->boolean('open_to_work')->default(true);
            $table->string('desired_position')->nullable();
            $table->decimal('desired_salary_min', 10, 2)->nullable();
            $table->decimal('desired_salary_max', 10, 2)->nullable();
            $table->enum('work_preference', ['remote', 'hybrid', 'onsite', 'any'])->nullable();
            $table->unsignedTinyInteger('years_of_experience')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('github_url')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_profiles');
    }
};
