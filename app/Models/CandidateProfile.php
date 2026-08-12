<?php

namespace App\Models;

use App\Enums\WorkPreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CandidateProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'headline',
        'summary',
        'city',
        'country',
        'profile_photo_path',
        'cv_file_path',
        'cv_parsed_data',
        'profile_strength_score',
        'open_to_work',
        'desired_position',
        'desired_salary_min',
        'desired_salary_max',
        'work_preference',
        'years_of_experience',
        'linkedin_url',
        'github_url',
        'portfolio_url',
    ];

    protected function casts(): array
    {
        return [
            'cv_parsed_data' => 'array',
            'profile_strength_score' => 'integer',
            'open_to_work' => 'boolean',
            'desired_salary_min' => 'decimal:2',
            'desired_salary_max' => 'decimal:2',
            'work_preference' => WorkPreference::class,
            'years_of_experience' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function candidateSkills(): HasMany
    {
        return $this->hasMany(CandidateSkill::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'candidate_skills')
            ->withPivot(['id', 'proficiency_level', 'years_of_experience'])
            ->withTimestamps();
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(CandidateExperience::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(CandidateEducation::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(CandidateCertification::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(CandidateProject::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function savedJobs(): HasMany
    {
        return $this->hasMany(SavedJob::class);
    }

    public function savedCompanies(): HasMany
    {
        return $this->hasMany(SavedCompany::class);
    }
}
