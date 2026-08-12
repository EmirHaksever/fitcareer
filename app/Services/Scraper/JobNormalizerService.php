<?php

declare(strict_types=1);

namespace App\Services\Scraper;

use App\Enums\EmploymentType;
use App\Enums\ExperienceLevel;
use App\Enums\JobOrigin;
use App\Enums\JobStatus;
use App\Enums\ScrapeStatus;
use App\Enums\TrustAnalysisStatus;
use App\Enums\TrustLabel;
use App\Enums\WorkType;
use App\Exceptions\ScraperFetchException;
use App\Models\JobSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class JobNormalizerService
{
    /**
     * @param  array<string, mixed>  $rawListing
     * @return array<string, mixed>
     */
    public function normalize(JobSource $source, array $rawListing): array
    {
        $provider = (string) ($source->config['provider'] ?? '');

        $attributes = match ($provider) {
            'remotive' => $this->normalizeRemotiveListing($source, $rawListing),
            'kariyer-net' => $this->normalizeKariyerNetListing($source, $rawListing),
            default => throw ScraperFetchException::unsupportedProvider($provider !== '' ? $provider : '(empty)'),
        };

        $attributes['content_hash'] = $this->generateContentHash($attributes);

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $normalizedListing
     */
    public function generateContentHash(array $normalizedListing): string
    {
        $payload = [
            'title' => $normalizedListing['title'] ?? null,
            'source_company_name' => $normalizedListing['source_company_name'] ?? null,
            'description' => isset($normalizedListing['description'])
                ? mb_substr(strip_tags((string) $normalizedListing['description']), 0, 2000)
                : null,
            'external_url' => $normalizedListing['external_url'] ?? null,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $rawListing
     * @return array<string, mixed>
     */
    private function normalizeRemotiveListing(JobSource $source, array $rawListing): array
    {
        $externalId = (string) ($rawListing['id'] ?? '');
        $title = trim((string) ($rawListing['title'] ?? ''));

        if ($externalId === '' || $title === '') {
            throw ScraperFetchException::invalidPayload('Remotive listing missing id or title.');
        }

        $description = trim(strip_tags((string) ($rawListing['description'] ?? '')));
        $location = $this->parseRemotiveLocation(isset($rawListing['candidate_required_location'])
            ? (string) $rawListing['candidate_required_location']
            : null);

        $publishedAt = $this->parseDateTime($rawListing['publication_date'] ?? null);

        return [
            'job_source_id' => $source->id,
            'source' => JobOrigin::Scraped,
            'source_company_name' => $this->nullableString($rawListing['company_name'] ?? null),
            'external_url' => $this->nullableString($rawListing['url'] ?? null),
            'external_id' => $externalId,
            'title' => $title,
            'slug' => $this->generateSlug($title, $externalId),
            'description' => $description !== '' ? $description : $title,
            'requirements' => null,
            'responsibilities' => null,
            'category' => $this->nullableString($rawListing['category'] ?? null),
            'employment_type' => $this->mapRemotiveEmploymentType($rawListing['job_type'] ?? null),
            'work_type' => WorkType::Remote,
            'experience_level' => null,
            'city' => $location['city'],
            'country' => $location['country'],
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => 'USD',
            'is_salary_visible' => filled($rawListing['salary'] ?? null),
            'application_deadline' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'status' => JobStatus::Published,
            'trust_score' => null,
            'trust_label' => TrustLabel::Unrated,
            'trust_analysis_status' => TrustAnalysisStatus::Pending,
            'scrape_status' => ScrapeStatus::Success,
            'scrape_error' => null,
            'published_at' => $publishedAt ?? now(),
            'expires_at' => null,
            'last_scraped_at' => now(),
            'last_seen_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $rawListing
     * @return array<string, mixed>
     */
    private function normalizeKariyerNetListing(JobSource $source, array $rawListing): array
    {
        $externalId = (string) ($rawListing['external_id'] ?? '');
        $title = trim((string) ($rawListing['title'] ?? ''));

        if ($externalId === '' || $title === '') {
            throw ScraperFetchException::invalidPayload('Kariyer.net listing missing external_id or title.');
        }

        $description = trim((string) ($rawListing['description'] ?? ''));
        $location = $this->parseKariyerNetLocation(isset($rawListing['location'])
            ? (string) $rawListing['location']
            : null);
        $publishedAt = $this->parseKariyerNetDate($rawListing['published_date_raw'] ?? null);
        $expiresAt = $this->parseKariyerNetDate($rawListing['closing_date_raw'] ?? null);

        return [
            'job_source_id' => $source->id,
            'source' => JobOrigin::Scraped,
            'source_company_name' => $this->nullableString($rawListing['company'] ?? null),
            'external_url' => $this->nullableString($rawListing['external_url'] ?? null),
            'external_id' => $externalId,
            'title' => $title,
            'slug' => $this->generateSlug($title, $externalId),
            'description' => $description !== '' ? $description : $title,
            'requirements' => null,
            'responsibilities' => null,
            'category' => null,
            'employment_type' => $this->mapKariyerNetEmploymentType($rawListing['employment_type_raw'] ?? null) ?? EmploymentType::FullTime,
            'work_type' => $this->mapKariyerNetWorkType($rawListing['work_model_raw'] ?? null) ?? WorkType::Onsite,
            'experience_level' => $this->mapKariyerNetExperienceLevel($rawListing['experience_level_raw'] ?? null),
            'city' => $location['city'],
            'country' => $location['country'],
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => 'TRY',
            'is_salary_visible' => false,
            'application_deadline' => $expiresAt,
            'contact_email' => null,
            'contact_phone' => null,
            'status' => JobStatus::Published,
            'trust_score' => null,
            'trust_label' => TrustLabel::Unrated,
            'trust_analysis_status' => TrustAnalysisStatus::Pending,
            'scrape_status' => ScrapeStatus::Success,
            'scrape_error' => null,
            'published_at' => $publishedAt ?? now(),
            'expires_at' => $expiresAt,
            'last_scraped_at' => now(),
            'last_seen_at' => now(),
        ];
    }

    /**
     * @return array{city: ?string, country: ?string}
     */
    private function parseKariyerNetLocation(?string $location): array
    {
        if ($location === null) {
            return ['city' => null, 'country' => 'Türkiye'];
        }

        $normalized = trim($location);

        if ($normalized === '') {
            return ['city' => null, 'country' => 'Türkiye'];
        }

        if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/u', $normalized, $match)) {
            return [
                'city' => trim($match[1]),
                'country' => 'Türkiye',
            ];
        }

        return [
            'city' => $normalized,
            'country' => 'Türkiye',
        ];
    }

    private function mapKariyerNetEmploymentType(mixed $value): ?EmploymentType
    {
        $normalized = mb_strtolower(trim((string) ($value ?? '')));

        return match (true) {
            str_contains($normalized, 'tam zaman') => EmploymentType::FullTime,
            str_contains($normalized, 'yarı zaman') => EmploymentType::PartTime,
            str_contains($normalized, 'staj') => EmploymentType::Internship,
            str_contains($normalized, 'sözleş') => EmploymentType::Contract,
            str_contains($normalized, 'serbest') => EmploymentType::Freelance,
            default => null,
        };
    }

    private function mapKariyerNetWorkType(mixed $value): ?WorkType
    {
        $normalized = mb_strtolower(trim((string) ($value ?? '')));

        return match (true) {
            str_contains($normalized, 'remote') || str_contains($normalized, 'uzaktan') => WorkType::Remote,
            str_contains($normalized, 'hibrit') => WorkType::Hybrid,
            str_contains($normalized, 'iş yerinde') || str_contains($normalized, 'ofisten') => WorkType::Onsite,
            default => null,
        };
    }

    private function mapKariyerNetExperienceLevel(mixed $value): ?ExperienceLevel
    {
        $normalized = mb_strtolower(trim((string) ($value ?? '')));

        if ($normalized === '') {
            return null;
        }

        return match (true) {
            str_contains($normalized, 'staj') => ExperienceLevel::Intern,
            str_contains($normalized, 'junior') || str_contains($normalized, 'yeni mezun') => ExperienceLevel::Entry,
            str_contains($normalized, '8 yıl') || str_contains($normalized, 'kıdemli') || str_contains($normalized, 'senior') => ExperienceLevel::Senior,
            str_contains($normalized, 'lead') || str_contains($normalized, 'müdür') => ExperienceLevel::Lead,
            str_contains($normalized, 'director') || str_contains($normalized, 'direktör') => ExperienceLevel::Executive,
            str_contains($normalized, 'tecrübeli / tecrübesiz') => null,
            default => null,
        };
    }

    private function parseKariyerNetDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d.m.Y', trim($value))->startOfDay();
        } catch (\Throwable) {
            return $this->parseDateTime($value);
        }
    }

    private function generateSlug(string $title, string $externalId): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'job';
        }

        return $base.'-'.$externalId;
    }

    /**
     * @return array{city: ?string, country: ?string}
     */
    private function parseRemotiveLocation(?string $location): array
    {
        if ($location === null) {
            return ['city' => null, 'country' => null];
        }

        $normalized = trim($location);

        if ($normalized === '' || strcasecmp($normalized, 'Worldwide') === 0) {
            return ['city' => null, 'country' => null];
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $normalized))));

        if (count($parts) >= 2) {
            return [
                'city' => $parts[0],
                'country' => $parts[count($parts) - 1],
            ];
        }

        return ['city' => null, 'country' => $parts[0]];
    }

    private function mapRemotiveEmploymentType(mixed $value): EmploymentType
    {
        return match (strtolower(trim((string) ($value ?? '')))) {
            'part_time' => EmploymentType::PartTime,
            'contract' => EmploymentType::Contract,
            'internship' => EmploymentType::Internship,
            'freelance' => EmploymentType::Freelance,
            default => EmploymentType::FullTime,
        };
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
