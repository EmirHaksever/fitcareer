<?php

namespace App\Models;

use App\Enums\ProficiencyLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateSkill extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_profile_id',
        'skill_id',
        'proficiency_level',
        'years_of_experience',
    ];

    protected function casts(): array
    {
        return [
            'proficiency_level' => ProficiencyLevel::class,
            'years_of_experience' => 'integer',
        ];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
