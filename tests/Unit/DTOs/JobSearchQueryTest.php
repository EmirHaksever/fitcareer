<?php

namespace Tests\Unit\DTOs;

use App\DTOs\JobSearchQuery;
use App\Enums\EmploymentType;
use App\Enums\WorkType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobSearchQueryTest extends TestCase
{
    #[Test]
    public function from_validated_input_maps_client_fields_and_ignores_candidate_profile_id(): void
    {
        $query = JobSearchQuery::fromValidatedInput([
            'keyword' => 'backend',
            'location' => 'Istanbul',
            'category' => 'engineering',
            'employment_type' => 'full_time',
            'work_type' => 'remote',
            'min_trust_score' => 70,
            'min_fit_score' => 60,
            'candidate_profile_id' => 999,
            'page' => 2,
            'per_page' => 20,
        ]);

        $this->assertSame('backend', $query->keyword);
        $this->assertSame('Istanbul', $query->location);
        $this->assertSame('engineering', $query->category);
        $this->assertSame(EmploymentType::FullTime, $query->employmentType);
        $this->assertSame(WorkType::Remote, $query->workType);
        $this->assertSame(70, $query->minTrustScore);
        $this->assertSame(60, $query->minFitScore);
        $this->assertNull($query->candidateProfileId);
        $this->assertSame(2, $query->page);
        $this->assertSame(20, $query->perPage);
    }

    #[Test]
    public function candidate_profile_id_can_only_be_set_server_side(): void
    {
        $query = JobSearchQuery::fromValidatedInput(['keyword' => 'php'])
            ->withCandidateProfileId(42);

        $this->assertSame(42, $query->candidateProfileId);
    }
}
