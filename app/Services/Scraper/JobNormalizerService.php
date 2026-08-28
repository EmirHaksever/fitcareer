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
use App\Services\Job\ExperienceLevelInferenceService;
use App\Services\Scraper\DTO\LocationInput;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class JobNormalizerService
{
    public function __construct(
        private readonly LocationClassificationService $locationClassifier = new LocationClassificationService,
        private readonly DescriptionNormalizerService $descriptionNormalizer = new DescriptionNormalizerService,
        private readonly ExperienceLevelInferenceService $experienceLevelInference = new ExperienceLevelInferenceService,
    ) {}
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
            'lever' => $this->normalizeLeverListing($source, $rawListing),
            'workable' => $this->normalizeWorkableListing($source, $rawListing),
            'ashby' => $this->normalizeAshbyListing($source, $rawListing),
            'greenhouse' => $this->normalizeGreenhouseListing($source, $rawListing),
            'recruitee' => $this->normalizeRecruiteeListing($source, $rawListing),
            default => throw ScraperFetchException::unsupportedProvider($provider !== '' ? $provider : '(empty)'),
        };

        if (($attributes['experience_level'] ?? null) === null) {
            $attributes['experience_level'] = $this->experienceLevelInference->inferFromTitle(
                (string) ($attributes['title'] ?? ''),
            );
        }

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

        $description = $this->descriptionNormalizer->normalize($rawListing['description'] ?? null);
        $rawLocation = isset($rawListing['candidate_required_location'])
            ? (string) $rawListing['candidate_required_location']
            : null;
        $location = $this->parseRemotiveLocation($rawLocation);
        $workType = WorkType::Remote;
        $location = $this->applyLocationClassification(
            $location['city'],
            $location['country'],
            $workType,
            array_filter([$rawLocation]),
        );

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
            'provider_updated_at' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $rawListing
     * @return array<string, mixed>
     */
    private function normalizeLeverListing(JobSource $source, array $rawListing): array
    {
        $externalId = (string) ($rawListing['id'] ?? '');
        $title = trim((string) ($rawListing['text'] ?? ''));

        if ($externalId === '' || $title === '') {
            throw ScraperFetchException::invalidPayload('Lever listing missing id or text.');
        }

        $publishedAt = $this->parseLeverEpochMs($rawListing['createdAt'] ?? null);
        $this->assertWithinMaxPostingAge($source, $publishedAt);

        $description = $this->extractLeverDescription($rawListing);
        $rawLocationStrings = $this->extractLeverLocationStrings($rawListing);
        $location = $this->parseLeverLocation($rawListing);
        $workType = $this->mapLeverWorkType($rawListing['workplaceType'] ?? null);
        $location = $this->applyLocationClassification(
            $location['city'],
            $location['country'],
            $workType,
            $rawLocationStrings,
        );
        $categories = is_array($rawListing['categories'] ?? null) ? $rawListing['categories'] : [];
        $salary = $this->parseLeverSalary($rawListing);

        $companyName = $this->nullableString($source->config['company_display_name'] ?? null)
            ?? $this->nullableString($source->name);

        return [
            'job_source_id' => $source->id,
            'source' => JobOrigin::Scraped,
            'source_company_name' => $companyName,
            'external_url' => $this->nullableString($rawListing['hostedUrl'] ?? $rawListing['applyUrl'] ?? null),
            'external_id' => $externalId,
            'title' => $title,
            'slug' => $this->generateSlug($title, $externalId),
            'description' => $description !== '' ? $description : $title,
            'requirements' => null,
            'responsibilities' => null,
            'category' => $this->nullableString($categories['team'] ?? $categories['department'] ?? $rawListing['department'] ?? null),
            'employment_type' => $this->mapLeverEmploymentType($categories['commitment'] ?? null),
            'work_type' => $workType,
            'experience_level' => null,
            'city' => $location['city'],
            'country' => $location['country'],
            'salary_min' => $salary['salary_min'],
            'salary_max' => $salary['salary_max'],
            'salary_currency' => $salary['salary_currency'],
            'is_salary_visible' => $salary['is_salary_visible'],
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
            'provider_updated_at' => $this->parseLeverProviderUpdatedAt($rawListing),
        ];
    }

    /**
     * Workable integration currently uses the public widget endpoint,
     * not the authenticated Workable SPI v3 Developer API.
     *
     * @param  array<string, mixed>  $rawListing
     * @return array<string, mixed>
     */
    private function normalizeWorkableListing(JobSource $source, array $rawListing): array
    {
        $externalId = (string) ($rawListing['shortcode'] ?? '');
        $title = trim((string) ($rawListing['title'] ?? ''));

        if ($externalId === '' || $title === '') {
            throw ScraperFetchException::invalidPayload('Workable listing missing shortcode or title.');
        }

        $publishedAt = $this->parseDateTime($rawListing['published_on'] ?? null);
        $this->assertWithinMaxPostingAge($source, $publishedAt);

        $description = $this->descriptionNormalizer->normalize($rawListing['description'] ?? null);
        $rawLocationStrings = $this->extractWorkableLocationStrings($rawListing);
        $location = $this->parseWorkableLocation($rawListing);
        $workType = $this->mapWorkableWorkType($rawListing['telecommuting'] ?? null);
        $location = $this->applyLocationClassification(
            $location['city'],
            $location['country'],
            $workType,
            $rawLocationStrings,
        );

        $companyName = $this->nullableString($source->config['company_display_name'] ?? null)
            ?? $this->nullableString($rawListing['_workable_account_name'] ?? null)
            ?? $this->nullableString($source->name);

        return [
            'job_source_id' => $source->id,
            'source' => JobOrigin::Scraped,
            'source_company_name' => $companyName,
            'external_url' => $this->nullableString($rawListing['url'] ?? null),
            'external_id' => $externalId,
            'title' => $title,
            'slug' => $this->generateSlug($title, $externalId),
            'description' => $description !== '' ? $description : $title,
            'requirements' => null,
            'responsibilities' => null,
            'category' => $this->nullableString($rawListing['department'] ?? null),
            'employment_type' => $this->mapWorkableEmploymentType($rawListing['employment_type'] ?? null),
            'work_type' => $workType,
            'experience_level' => null,
            'city' => $location['city'],
            'country' => $location['country'],
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => 'USD',
            'is_salary_visible' => false,
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
            'provider_updated_at' => $this->parseWorkableProviderUpdatedAt($rawListing),
        ];
    }

    /**
     * Ashby public Job Posting API normalizer.
     *
     * @param  array<string, mixed>  $rawListing
     * @return array<string, mixed>
     */
    private function normalizeAshbyListing(JobSource $source, array $rawListing): array
    {
        $externalId = (string) ($rawListing['id'] ?? '');
        $title = trim((string) ($rawListing['title'] ?? ''));

        if ($externalId === '' || $title === '') {
            throw ScraperFetchException::invalidPayload('Ashby listing missing id or title.');
        }

        $publishedAt = $this->parseDateTime($rawListing['publishedAt'] ?? null);
        $this->assertWithinMaxPostingAge($source, $publishedAt);

        $description = $this->extractAshbyDescription($rawListing);
        $rawLocationStrings = $this->extractAshbyLocationStrings($rawListing);
        $location = $this->parseAshbyLocation($rawListing);
        $workType = $this->mapAshbyWorkType($rawListing);

        $location = $this->applyLocationClassification(
            $location['city'],
            $location['country'],
            $workType,
            $rawLocationStrings,
        );

        $companyName = $this->nullableString($source->config['company_display_name'] ?? null)
            ?? $this->nullableString($source->name);

        return [
            'job_source_id' => $source->id,
            'source' => JobOrigin::Scraped,
            'source_company_name' => $companyName,
            'external_url' => $this->nullableString($rawListing['jobUrl'] ?? $rawListing['applyUrl'] ?? null),
            'external_id' => $externalId,
            'title' => $title,
            'slug' => $this->generateSlug($title, $externalId),
            'description' => $description !== '' ? $description : $title,
            'requirements' => null,
            'responsibilities' => null,
            'category' => $this->nullableString($rawListing['department'] ?? $rawListing['team'] ?? null),
            'employment_type' => $this->mapAshbyEmploymentType($rawListing['employmentType'] ?? null),
            'work_type' => $workType,
            'experience_level' => null,
            'city' => $location['city'],
            'country' => $location['country'],
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => 'USD',
            'is_salary_visible' => false,
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
            'provider_updated_at' => null,
        ];
    }

    /**
     * Greenhouse public Job Board API normalizer.
     *
     * @param  array<string, mixed>  $rawListing
     * @return array<string, mixed>
     */
    private function normalizeGreenhouseListing(JobSource $source, array $rawListing): array
    {
        $externalId = (string) ($rawListing['id'] ?? '');
        $title = trim((string) ($rawListing['title'] ?? ''));

        if ($externalId === '' || $title === '') {
            throw ScraperFetchException::invalidPayload('Greenhouse listing missing id or title.');
        }

        $publishedAt = $this->parseGreenhousePublishedAt($rawListing);
        $this->assertWithinMaxPostingAge($source, $publishedAt);

        $description = $this->extractGreenhouseDescription($rawListing);
        $rawLocationStrings = $this->extractGreenhouseLocationStrings($rawListing);
        $location = $this->parseGreenhouseLocation($rawListing);
        $workType = WorkType::Onsite;

        $location = $this->applyLocationClassification(
            $location['city'],
            $location['country'],
            $workType,
            $rawLocationStrings,
        );

        $companyName = $this->nullableString($source->config['company_display_name'] ?? null)
            ?? $this->nullableString($rawListing['company_name'] ?? null)
            ?? $this->nullableString($source->name);

        $departments = is_array($rawListing['departments'] ?? null) ? $rawListing['departments'] : [];
        $firstDepartment = isset($departments[0]) && is_array($departments[0])
            ? $this->nullableString($departments[0]['name'] ?? null)
            : null;

        return [
            'job_source_id' => $source->id,
            'source' => JobOrigin::Scraped,
            'source_company_name' => $companyName,
            'external_url' => $this->nullableString($rawListing['absolute_url'] ?? null),
            'external_id' => $externalId,
            'title' => $title,
            'slug' => $this->generateSlug($title, $externalId),
            'description' => $description !== '' ? $description : $title,
            'requirements' => null,
            'responsibilities' => null,
            'category' => $firstDepartment,
            'employment_type' => EmploymentType::FullTime,
            'work_type' => $workType,
            'experience_level' => null,
            'city' => $location['city'],
            'country' => $location['country'],
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => 'USD',
            'is_salary_visible' => false,
            'application_deadline' => $this->parseDateTime($rawListing['application_deadline'] ?? null),
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
            'provider_updated_at' => $this->parseDateTime($rawListing['updated_at'] ?? null),
        ];
    }

    /**
     * Recruitee Careers Site API normalizer.
     *
     * @param  array<string, mixed>  $rawListing
     * @return array<string, mixed>
     */
    private function normalizeRecruiteeListing(JobSource $source, array $rawListing): array
    {
        $externalId = (string) ($rawListing['id'] ?? '');
        $title = trim((string) ($rawListing['title'] ?? ''));

        if ($externalId === '' || $title === '') {
            throw ScraperFetchException::invalidPayload('Recruitee listing missing id or title.');
        }

        $publishedAt = $this->parseDateTime($rawListing['published_at'] ?? $rawListing['created_at'] ?? null);
        $this->assertWithinMaxPostingAge($source, $publishedAt);

        $description = $this->descriptionNormalizer->normalize($rawListing['description'] ?? null);
        $rawLocationStrings = $this->extractRecruiteeLocationStrings($rawListing);
        $location = $this->parseRecruiteeLocation($rawListing);
        $workType = $this->mapRecruiteeWorkType($rawListing);

        $location = $this->applyLocationClassification(
            $location['city'],
            $location['country'],
            $workType,
            $rawLocationStrings,
        );

        $companyName = $this->nullableString($source->config['company_display_name'] ?? null)
            ?? $this->nullableString($rawListing['company_name'] ?? null)
            ?? $this->nullableString($source->name);

        return [
            'job_source_id' => $source->id,
            'source' => JobOrigin::Scraped,
            'source_company_name' => $companyName,
            'external_url' => $this->nullableString($rawListing['careers_apply_url'] ?? $rawListing['careers_url'] ?? null),
            'external_id' => $externalId,
            'title' => $title,
            'slug' => $this->generateSlug($title, $externalId),
            'description' => $description !== '' ? $description : $title,
            'requirements' => null,
            'responsibilities' => null,
            'category' => $this->nullableString($rawListing['category_code'] ?? $rawListing['department'] ?? null),
            'employment_type' => $this->mapRecruiteeEmploymentType($rawListing['employment_type_code'] ?? null),
            'work_type' => $workType,
            'experience_level' => null,
            'city' => $location['city'],
            'country' => $location['country'],
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => 'TRY',
            'is_salary_visible' => false,
            'application_deadline' => $this->parseDateTime($rawListing['close_at'] ?? null),
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
            'provider_updated_at' => $this->parseDateTime($rawListing['updated_at'] ?? null),
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

        $description = $this->descriptionNormalizer->normalize($rawListing['description'] ?? null);
        $rawLocation = isset($rawListing['location'])
            ? (string) $rawListing['location']
            : null;
        $location = $this->parseKariyerNetLocation($rawLocation);
        $workType = $this->mapKariyerNetWorkType($rawListing['work_model_raw'] ?? null) ?? WorkType::Onsite;
        $location = $this->applyLocationClassification(
            $location['city'],
            $location['country'],
            $workType,
            array_filter([$rawLocation]),
        );
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
            'work_type' => $workType,
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
            'provider_updated_at' => null,
        ];
    }

    /**
     * @param  list<?string>  $rawLocationStrings
     * @return array{city: ?string, country: ?string}
     */
    private function applyLocationClassification(
        ?string $city,
        ?string $country,
        WorkType $workType,
        array $rawLocationStrings,
    ): array {
        $result = $this->locationClassifier->classify(
            LocationInput::fromSignals($city, $country, $workType, $rawLocationStrings),
        );

        return [
            'city' => $result->city,
            'country' => $result->country,
        ];
    }

    /**
     * @param  array<string, mixed>  $rawListing
     * @return list<string>
     */
    private function extractLeverLocationStrings(array $rawListing): array
    {
        $strings = [];
        $categories = is_array($rawListing['categories'] ?? null) ? $rawListing['categories'] : [];

        if (is_string($categories['location'] ?? null) && trim($categories['location']) !== '') {
            $strings[] = trim($categories['location']);
        }

        $allLocations = $categories['allLocations'] ?? $rawListing['allLocations'] ?? [];

        if (is_array($allLocations)) {
            foreach ($allLocations as $location) {
                if (is_string($location) && trim($location) !== '') {
                    $strings[] = trim($location);
                }
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * @param  array<string, mixed>  $rawListing
     * @return list<string>
     */
    private function extractWorkableLocationStrings(array $rawListing): array
    {
        $strings = [];

        foreach (['city', 'country'] as $field) {
            $value = $this->nullableString($rawListing[$field] ?? null);

            if ($value !== null) {
                $strings[] = $value;
            }
        }

        $locations = $rawListing['locations'] ?? null;

        if (is_array($locations)) {
            foreach ($locations as $location) {
                if (! is_array($location)) {
                    continue;
                }

                foreach (['city', 'country'] as $field) {
                    $value = $this->nullableString($location[$field] ?? null);

                    if ($value !== null) {
                        $strings[] = $value;
                    }
                }
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * @param  array<string, mixed>  $rawListing
     */
    private function extractAshbyDescription(array $rawListing): string
    {
        $plain = trim((string) ($rawListing['descriptionPlain'] ?? ''));

        if ($plain !== '') {
            return $this->descriptionNormalizer->normalize($plain);
        }

        return $this->descriptionNormalizer->normalize($rawListing['descriptionHtml'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $rawListing
     * @return list<string>
     */
    private function extractAshbyLocationStrings(array $rawListing): array
    {
        $strings = [];

        foreach (['location', 'workplaceType'] as $field) {
            $value = $this->nullableString($rawListing[$field] ?? null);

            if ($value !== null) {
                $strings[] = $value;
            }
        }

        if (($rawListing['isRemote'] ?? false) === true) {
            $strings[] = 'Remote';
        }

        $address = is_array($rawListing['address'] ?? null) ? $rawListing['address'] : [];
        $postal = is_array($address['postalAddress'] ?? null) ? $address['postalAddress'] : [];

        foreach (['addressLocality', 'addressRegion', 'addressCountry'] as $field) {
            $value = $this->nullableString($postal[$field] ?? null);

            if ($value !== null) {
                $strings[] = $value;
            }
        }

        $secondaryLocations = $rawListing['secondaryLocations'] ?? null;

        if (is_array($secondaryLocations)) {
            foreach ($secondaryLocations as $secondaryLocation) {
                if (is_string($secondaryLocation) && trim($secondaryLocation) !== '') {
                    $strings[] = trim($secondaryLocation);
                }
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * @param  array<string, mixed>  $rawListing
     * @return array{city: ?string, country: ?string}
     */
    private function parseAshbyLocation(array $rawListing): array
    {
        $address = is_array($rawListing['address'] ?? null) ? $rawListing['address'] : [];
        $postal = is_array($address['postalAddress'] ?? null) ? $address['postalAddress'] : [];

        $city = $this->nullableString($postal['addressLocality'] ?? null)
            ?? $this->nullableString($rawListing['location'] ?? null);
        $country = $this->nullableString($postal['addressCountry'] ?? null);

        return [
            'city' => $city,
            'country' => $country,
        ];
    }

    private function mapAshbyEmploymentType(mixed $value): EmploymentType
    {
        $normalized = strtolower(trim((string) ($value ?? '')));

        return match ($normalized) {
            'parttime', 'part_time', 'part-time' => EmploymentType::PartTime,
            'contract', 'temporary' => EmploymentType::Contract,
            'intern', 'internship' => EmploymentType::Internship,
            'freelance' => EmploymentType::Freelance,
            default => EmploymentType::FullTime,
        };
    }

    /**
     * @param  array<string, mixed>  $rawListing
     */
    private function mapAshbyWorkType(array $rawListing): WorkType
    {
        $workplaceType = strtolower(trim((string) ($rawListing['workplaceType'] ?? '')));

        return match (true) {
            in_array($workplaceType, ['remote'], true) => WorkType::Remote,
            in_array($workplaceType, ['hybrid'], true) => WorkType::Hybrid,
            in_array($workplaceType, ['onsite', 'on-site', 'on site'], true) => WorkType::Onsite,
            ($rawListing['isRemote'] ?? false) === true => WorkType::Remote,
            default => WorkType::Onsite,
        };
    }

    /**
     * @param  array<string, mixed>  $rawListing
     * @return list<string>
     */
    private function extractRecruiteeLocationStrings(array $rawListing): array
    {
        $strings = [];

        foreach (['location', 'city', 'country', 'country_code', 'state_name'] as $field) {
            $value = $this->nullableString($rawListing[$field] ?? null);

            if ($value !== null) {
                $strings[] = $value;
            }
        }

        $locations = $rawListing['locations'] ?? null;

        if (is_array($locations)) {
            foreach ($locations as $location) {
                if (! is_array($location)) {
                    continue;
                }

                foreach (['name', 'city', 'state', 'country', 'country_code'] as $field) {
                    $value = $this->nullableString($location[$field] ?? null);

                    if ($value !== null) {
                        $strings[] = $value;
                    }
                }
            }
        }

        if (($rawListing['remote'] ?? false) === true) {
            $strings[] = 'Remote';
        }

        if (($rawListing['hybrid'] ?? false) === true) {
            $strings[] = 'Hybrid';
        }

        return array_values(array_unique($strings));
    }

    /**
     * @param  array<string, mixed>  $rawListing
     * @return array{city: ?string, country: ?string}
     */
    private function parseRecruiteeLocation(array $rawListing): array
    {
        $city = $this->nullableString($rawListing['city'] ?? null);
        $country = $this->nullableString($rawListing['country'] ?? null);

        $locations = $rawListing['locations'] ?? null;

        if (is_array($locations)) {
            foreach ($locations as $location) {
                if (! is_array($location)) {
                    continue;
                }

                $city ??= $this->nullableString($location['city'] ?? null);
                $country ??= $this->nullableString($location['country'] ?? null);
            }
        }

        if ($country === null) {
            $countryCode = strtoupper(trim((string) ($rawListing['country_code'] ?? '')));

            if ($countryCode === 'TR') {
                $country = 'Türkiye';
            }
        }

        return [
            'city' => $city,
            'country' => $country,
        ];
    }

    /**
     * @param  array<string, mixed>  $rawListing
     */
    private function mapRecruiteeWorkType(array $rawListing): WorkType
    {
        return match (true) {
            ($rawListing['remote'] ?? false) === true => WorkType::Remote,
            ($rawListing['hybrid'] ?? false) === true => WorkType::Hybrid,
            ($rawListing['on_site'] ?? false) === true => WorkType::Onsite,
            default => WorkType::Onsite,
        };
    }

    private function mapRecruiteeEmploymentType(mixed $value): EmploymentType
    {
        $normalized = strtolower(str_replace('-', '_', trim((string) ($value ?? ''))));

        return match (true) {
            str_contains($normalized, 'part') && str_contains($normalized, 'time') => EmploymentType::PartTime,
            str_contains($normalized, 'intern') => EmploymentType::Internship,
            str_contains($normalized, 'contract') || str_contains($normalized, 'temporary') => EmploymentType::Contract,
            str_contains($normalized, 'freelance') => EmploymentType::Freelance,
            default => EmploymentType::FullTime,
        };
    }

    /**
     * @param  array<string, mixed>  $rawListing
     */
    private function extractGreenhouseDescription(array $rawListing): string
    {
        return $this->descriptionNormalizer->normalize($rawListing['content'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $rawListing
     */
    private function parseGreenhousePublishedAt(array $rawListing): ?Carbon
    {
        $publishedAt = $this->parseDateTime($rawListing['first_published'] ?? null);

        if ($publishedAt !== null) {
            return $publishedAt;
        }

        return $this->parseDateTime($rawListing['updated_at'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $rawListing
     * @return list<string>
     */
    private function extractGreenhouseLocationStrings(array $rawListing): array
    {
        $strings = [];

        $location = $rawListing['location'] ?? null;

        if (is_array($location)) {
            $name = $this->nullableString($location['name'] ?? null);

            if ($name !== null) {
                $strings[] = $name;
            }
        }

        $offices = $rawListing['offices'] ?? null;

        if (is_array($offices)) {
            foreach ($offices as $office) {
                if (! is_array($office)) {
                    continue;
                }

                foreach (['location', 'name'] as $field) {
                    $value = $this->nullableString($office[$field] ?? null);

                    if ($value !== null) {
                        $strings[] = $value;
                    }
                }
            }
        }

        return array_values(array_unique($strings));
    }

    /**
     * @param  array<string, mixed>  $rawListing
     * @return array{city: ?string, country: ?string}
     */
    private function parseGreenhouseLocation(array $rawListing): array
    {
        $locationString = $this->extractGreenhousePrimaryLocationString($rawListing);

        if ($locationString === null) {
            return ['city' => null, 'country' => null];
        }

        return $this->parseGreenhouseLocationString($locationString);
    }

    /**
     * @param  array<string, mixed>  $rawListing
     */
    private function extractGreenhousePrimaryLocationString(array $rawListing): ?string
    {
        $offices = $rawListing['offices'] ?? null;

        if (is_array($offices)) {
            foreach ($offices as $office) {
                if (! is_array($office)) {
                    continue;
                }

                $officeLocation = $this->nullableString($office['location'] ?? null);

                if ($officeLocation !== null) {
                    return $officeLocation;
                }
            }
        }

        $location = $rawListing['location'] ?? null;

        if (is_array($location)) {
            return $this->nullableString($location['name'] ?? null);
        }

        return null;
    }

    /**
     * @return array{city: ?string, country: ?string}
     */
    private function parseGreenhouseLocationString(string $location): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $location))));

        if ($parts === []) {
            return ['city' => null, 'country' => null];
        }

        if (count($parts) === 1) {
            return ['city' => $parts[0], 'country' => null];
        }

        if (count($parts) >= 3) {
            return [
                'city' => $parts[count($parts) - 2],
                'country' => $parts[count($parts) - 1],
            ];
        }

        return [
            'city' => $parts[0],
            'country' => $parts[1],
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

    private function mapLeverEmploymentType(mixed $value): EmploymentType
    {
        $normalized = strtolower(str_replace('-', ' ', trim((string) ($value ?? ''))));

        return match (true) {
            str_contains($normalized, 'part') => EmploymentType::PartTime,
            str_contains($normalized, 'contract') => EmploymentType::Contract,
            str_contains($normalized, 'intern') => EmploymentType::Internship,
            str_contains($normalized, 'freelance') => EmploymentType::Freelance,
            default => EmploymentType::FullTime,
        };
    }

    private function mapLeverWorkType(mixed $value): WorkType
    {
        return match (strtolower(trim((string) ($value ?? '')))) {
            'remote' => WorkType::Remote,
            'hybrid' => WorkType::Hybrid,
            default => WorkType::Onsite,
        };
    }

    private function mapWorkableEmploymentType(mixed $value): EmploymentType
    {
        $normalized = strtolower(str_replace('-', ' ', trim((string) ($value ?? ''))));

        if ($normalized === '') {
            return EmploymentType::FullTime;
        }

        return match (true) {
            str_contains($normalized, 'part') => EmploymentType::PartTime,
            str_contains($normalized, 'contract') => EmploymentType::Contract,
            str_contains($normalized, 'intern') => EmploymentType::Internship,
            str_contains($normalized, 'freelance') => EmploymentType::Freelance,
            default => EmploymentType::FullTime,
        };
    }

    private function mapWorkableWorkType(mixed $value): WorkType
    {
        if (is_bool($value)) {
            return $value ? WorkType::Remote : WorkType::Onsite;
        }

        $normalized = strtolower(trim((string) ($value ?? '')));

        return match (true) {
            in_array($normalized, ['true', '1', 'yes', 'remote'], true) => WorkType::Remote,
            str_contains($normalized, 'hybrid') => WorkType::Hybrid,
            default => WorkType::Onsite,
        };
    }

    /**
     * @param  array<string, mixed>  $rawListing
     * @return array{city: ?string, country: ?string}
     */
    private function parseWorkableLocation(array $rawListing): array
    {
        $city = $this->nullableString($rawListing['city'] ?? null);
        $country = $this->nullableString($rawListing['country'] ?? null);

        if ($city !== null || $country !== null) {
            return ['city' => $city, 'country' => $country];
        }

        $locations = $rawListing['locations'] ?? null;

        if (! is_array($locations) || ! isset($locations[0]) || ! is_array($locations[0])) {
            return ['city' => null, 'country' => null];
        }

        $first = $locations[0];

        return [
            'city' => $this->nullableString($first['city'] ?? null),
            'country' => $this->nullableString($first['country'] ?? null),
        ];
    }

    /**
     * @return array{city: ?string, country: ?string}
     */
    private function parseLeverLocation(array $rawListing): array
    {
        $categories = is_array($rawListing['categories'] ?? null) ? $rawListing['categories'] : [];

        $location = $categories['location'] ?? null;

        if (! is_string($location) || trim($location) === '') {
            $allLocations = $categories['allLocations'] ?? $rawListing['allLocations'] ?? [];

            if (is_array($allLocations) && isset($allLocations[0]) && is_string($allLocations[0])) {
                $location = $allLocations[0];
            }
        }

        return $this->parseRemotiveLocation(is_string($location) ? $location : null);
    }

    private function extractLeverDescription(array $rawListing): string
    {
        $plain = trim((string) ($rawListing['descriptionPlain'] ?? ''));

        if ($plain !== '') {
            return $this->descriptionNormalizer->normalize($plain);
        }

        return $this->descriptionNormalizer->normalize($rawListing['description'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $rawListing
     */
    private function parseLeverProviderUpdatedAt(array $rawListing): ?Carbon
    {
        return $this->parseLeverEpochMs($rawListing['updatedAt'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $rawListing
     */
    private function parseWorkableProviderUpdatedAt(array $rawListing): ?Carbon
    {
        return $this->parseDateTime($rawListing['updated_at'] ?? null);
    }

    private function parseLeverEpochMs(mixed $value): ?Carbon
    {
        if (! is_numeric($value)) {
            return null;
        }

        $milliseconds = (int) $value;

        if ($milliseconds <= 0) {
            return null;
        }

        try {
            return Carbon::createFromTimestampMs($milliseconds);
        } catch (\Throwable) {
            return null;
        }
    }

    private function assertWithinMaxPostingAge(JobSource $source, ?Carbon $publishedAt): void
    {
        $maxDays = $source->config['max_posting_age_days'] ?? null;

        if (! is_numeric($maxDays) || (int) $maxDays <= 0) {
            return;
        }

        if ($publishedAt === null) {
            return;
        }

        if ($publishedAt->lt(now()->subDays((int) $maxDays))) {
            throw ScraperFetchException::invalidPayload(
                'Lever posting exceeds configured max_posting_age_days ('.(int) $maxDays.').'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $rawListing
     * @return array{salary_min: ?float, salary_max: ?float, salary_currency: string, is_salary_visible: bool}
     */
    private function parseLeverSalary(array $rawListing): array
    {
        $defaults = [
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => 'USD',
            'is_salary_visible' => false,
        ];

        $salaryRange = $rawListing['salaryRange'] ?? null;

        if (is_array($salaryRange)) {
            $min = isset($salaryRange['min']) && is_numeric($salaryRange['min']) ? (float) $salaryRange['min'] : null;
            $max = isset($salaryRange['max']) && is_numeric($salaryRange['max']) ? (float) $salaryRange['max'] : null;
            $currency = is_string($salaryRange['currency'] ?? null) ? strtoupper(trim($salaryRange['currency'])) : 'USD';

            if ($min !== null || $max !== null) {
                return [
                    'salary_min' => $min,
                    'salary_max' => $max,
                    'salary_currency' => $currency !== '' ? $currency : 'USD',
                    'is_salary_visible' => true,
                ];
            }
        }

        $salaryDescription = trim((string) ($rawListing['salaryDescription'] ?? ''));

        if ($salaryDescription !== '') {
            return [
                ...$defaults,
                'is_salary_visible' => true,
            ];
        }

        return $defaults;
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
