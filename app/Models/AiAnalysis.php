<?php

namespace App\Models;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'job_id',
        'candidate_profile_id',
        'score',
        'label',
        'summary',
        'details',
        'ai_model',
        'analysis_version',
        'prompt_version',
        'raw_response',
        'status',
        'is_latest',
        'analyzed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AiAnalysisType::class,
            'score' => 'integer',
            'details' => 'array',
            'raw_response' => 'array',
            'status' => AiAnalysisStatus::class,
            'is_latest' => 'boolean',
            'analyzed_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
