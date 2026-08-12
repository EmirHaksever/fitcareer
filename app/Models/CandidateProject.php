<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'candidate_profile_id',
        'title',
        'description',
        'project_url',
        'repository_url',
        'start_date',
        'end_date',
        'technologies',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'technologies' => 'array',
        ];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }
}
