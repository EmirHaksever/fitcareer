<?php

namespace App\Models;

use App\Enums\JobReportReason;
use App\Enums\JobReportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'user_id',
        'reason',
        'description',
        'status',
        'admin_note',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => JobReportReason::class,
            'status' => JobReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
