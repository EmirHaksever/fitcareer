<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'candidate_profile_id',
        'job_id',
        'resume_snapshot_path',
        'cover_letter',
        'status',
        'match_score',
        'trust_score',
        'applied_at',
        'status_updated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'match_score' => 'integer',
            'trust_score' => 'integer',
            'applied_at' => 'datetime',
            'status_updated_at' => 'datetime',
        ];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class);
    }
}
