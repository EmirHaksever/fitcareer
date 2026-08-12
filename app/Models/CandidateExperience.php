<?php

namespace App\Models;

use App\Enums\EmploymentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateExperience extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_profile_id',
        'company_name',
        'position_title',
        'employment_type',
        'location',
        'is_current',
        'start_date',
        'end_date',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'is_current' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
