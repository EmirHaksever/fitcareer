<?php

namespace App\Models;

use App\Enums\SkillImportance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobSkill extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'skill_id',
        'importance',
    ];

    protected function casts(): array
    {
        return [
            'importance' => SkillImportance::class,
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
