<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_companies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->timestamp('saved_at');
            $table->timestamps();

            $table->unique(['candidate_profile_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_companies');
    }
};
