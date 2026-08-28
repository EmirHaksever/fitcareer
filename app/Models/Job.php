<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\ExperienceLevel;
use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Enums\ScrapeStatus;
use App\Enums\TrustAnalysisStatus;
use App\Enums\TrustLabel;
use App\Enums\WorkType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Job extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'job_source_id',
        'posted_by',
        'source',
        'source_company_name',
        'external_url',
        'external_id',
        'title',
        'slug',
        'description',
        'requirements',
        'responsibilities',
        'category',
        'employment_type',
        'work_type',
        'experience_level',
        'city',
        'country',
        'salary_min',
        'salary_max',
        'salary_currency',
        'is_salary_visible',
        'application_deadline',
        'contact_email',
        'contact_phone',
        'status',
        'trust_score',
        'trust_label',
        'trust_analysis_status',
        'content_hash',
        'last_scraped_at',
        'last_seen_at',
        'first_seen_at',
        'provider_updated_at',
        'scrape_status',
        'scrape_error',
        'views_count',
        'applications_count',
        'published_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => JobOrigin::class,
            'employment_type' => EmploymentType::class,
            'work_type' => WorkType::class,
            'experience_level' => ExperienceLevel::class,
            'salary_min' => 'decimal:2',
            'salary_max' => 'decimal:2',
            'is_salary_visible' => 'boolean',
            'fit_score_weights' => 'array',
            'application_deadline' => 'date',
            'status' => JobStatus::class,
            'trust_score' => 'integer',
            'trust_label' => TrustLabel::class,
            'trust_analysis_status' => TrustAnalysisStatus::class,
            'last_scraped_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'provider_updated_at' => 'datetime',
            'scrape_status' => ScrapeStatus::class,
            'views_count' => 'integer',
            'applications_count' => 'integer',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sourceProvider(): BelongsTo
    {
        return $this->belongsTo(JobSource::class, 'job_source_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function jobSkills(): HasMany
    {
        return $this->hasMany(JobSkill::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'job_skills')
            ->withPivot(['id', 'importance'])
            ->withTimestamps();
    }

    public function refreshRequests(): HasMany
    {
        return $this->hasMany(JobRefreshRequest::class);
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

    public function reports(): HasMany
    {
        return $this->hasMany(JobReport::class);
    }
}
