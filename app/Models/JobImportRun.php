<?php

namespace App\Models;

use App\Enums\ImportRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobImportRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_source_id',
        'status',
        'started_at',
        'finished_at',
        'items_found',
        'items_created',
        'items_updated',
        'items_skipped',
        'items_failed',
        'error_log',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'items_found' => 'integer',
            'items_created' => 'integer',
            'items_updated' => 'integer',
            'items_skipped' => 'integer',
            'items_failed' => 'integer',
            'error_log' => 'array',
        ];
    }

    public function jobSource(): BelongsTo
    {
        return $this->belongsTo(JobSource::class);
    }
}
