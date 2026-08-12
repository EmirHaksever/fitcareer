<?php

namespace App\Models;

use App\Enums\JobSourceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'base_url',
        'type',
        'is_active',
        'config',
        'last_run_at',
        'last_success_at',
        'last_failure_at',
        'last_error',
        'consecutive_failures',
        'last_items_found',
        'last_items_created',
        'last_items_updated',
    ];

    protected function casts(): array
    {
        return [
            'type' => JobSourceType::class,
            'is_active' => 'boolean',
            'config' => 'array',
            'last_run_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'consecutive_failures' => 'integer',
            'last_items_found' => 'integer',
            'last_items_created' => 'integer',
            'last_items_updated' => 'integer',
        ];
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function importRuns(): HasMany
    {
        return $this->hasMany(JobImportRun::class);
    }
}
