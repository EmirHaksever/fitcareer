<?php

namespace App\Models;

use App\Enums\CompanySize;
use App\Enums\CompanyVerificationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'logo_path',
        'website',
        'industry',
        'company_size',
        'founded_year',
        'description',
        'city',
        'country',
        'tax_number',
        'is_verified',
        'verification_status',
        'trust_score',
        'social_links',
        'contact_email',
        'contact_phone',
    ];

    protected function casts(): array
    {
        return [
            'company_size' => CompanySize::class,
            'founded_year' => 'integer',
            'is_verified' => 'boolean',
            'verification_status' => CompanyVerificationStatus::class,
            'trust_score' => 'integer',
            'social_links' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function savedCompanies(): HasMany
    {
        return $this->hasMany(SavedCompany::class);
    }
}
