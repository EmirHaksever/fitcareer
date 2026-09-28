<?php

namespace Tests\Unit\Job;

use App\Enums\AiAnalysisStatus;
use App\Enums\AiAnalysisType;
use App\Enums\TrustAnalysisStatus;
use App\Enums\TrustLabel;
use App\Models\AiAnalysis;
use App\Models\Job;
use App\Support\JobScorePresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobScorePresenterTest extends TestCase
{
    #[Test]
    public function pending_trust_analysis_hides_trust_score(): void
    {
        $job = new Job([
            'trust_score' => 80,
            'trust_label' => TrustLabel::Unrated,
            'trust_analysis_status' => TrustAnalysisStatus::Pending,
        ]);

        $fields = JobScorePresenter::trustFields($job);

        $this->assertNull($fields['trust_score']);
        $this->assertSame('pending', $fields['trust_analysis_status']);
    }

    #[Test]
    public function completed_trust_analysis_exposes_trust_score(): void
    {
        $job = new Job([
            'trust_score' => 88,
            'trust_label' => TrustLabel::Verified,
            'trust_analysis_status' => TrustAnalysisStatus::Completed,
        ]);

        $fields = JobScorePresenter::trustFields($job);

        $this->assertSame(88, $fields['trust_score']);
        $this->assertSame('verified', $fields['trust_label']);
    }

    #[Test]
    public function fit_fields_are_null_without_candidate_context(): void
    {
        $job = new Job;

        $fields = JobScorePresenter::fitFields($job, null);

        $this->assertNull($fields['fit_score']);
        $this->assertNull($fields['fit_analysis_status']);
    }

    #[Test]
    public function completed_fit_analysis_exposes_score(): void
    {
        $job = Job::factory()->make();
        $analysis = new AiAnalysis([
            'type' => AiAnalysisType::CvJobFit,
            'candidate_profile_id' => 5,
            'score' => 72,
            'status' => AiAnalysisStatus::Completed,
            'is_latest' => true,
        ]);
        $job->setRelation('analyses', collect([$analysis]));

        $fields = JobScorePresenter::fitFields($job, 5);

        $this->assertSame(72, $fields['fit_score']);
        $this->assertSame('completed', $fields['fit_analysis_status']);
    }

    #[Test]
    public function fit_details_are_null_without_candidate_context(): void
    {
        $job = new Job;

        $fields = JobScorePresenter::fitDetailsFields($job, null);

        $this->assertNull($fields['fit_details']);
    }

    #[Test]
    public function fit_details_are_null_when_analysis_is_pending(): void
    {
        $job = Job::factory()->make();
        $analysis = new AiAnalysis([
            'type' => AiAnalysisType::CvJobFit,
            'candidate_profile_id' => 5,
            'score' => 72,
            'status' => AiAnalysisStatus::Pending,
            'is_latest' => true,
            'details' => [
                'signals' => [
                    'required_skills' => ['score' => 100, 'confidence' => 1.0, 'evidence' => []],
                ],
            ],
        ]);
        $job->setRelation('analyses', collect([$analysis]));

        $fields = JobScorePresenter::fitDetailsFields($job, 5);

        $this->assertNull($fields['fit_details']);
    }

    #[Test]
    public function completed_fit_analysis_exposes_fit_details(): void
    {
        $job = Job::factory()->make();
        $analysis = new AiAnalysis([
            'type' => AiAnalysisType::CvJobFit,
            'candidate_profile_id' => 5,
            'score' => 72,
            'status' => AiAnalysisStatus::Completed,
            'is_latest' => true,
            'details' => [
                'signals' => [
                    'required_skills' => [
                        'score' => 75,
                        'confidence' => 1.0,
                        'evidence' => [
                            'required_count' => 4,
                            'matched_count' => 3,
                            'matched_skills' => ['Laravel'],
                            'missing_skills' => ['Docker'],
                        ],
                    ],
                    'work_type' => [
                        'score' => 100,
                        'confidence' => 1.0,
                        'evidence' => ['match_type' => 'exact'],
                    ],
                ],
                'confidence' => 1.0,
                'fit_version' => 'fit-v1',
                'input_fingerprint' => 'abc123',
            ],
        ]);
        $job->setRelation('analyses', collect([$analysis]));

        $fields = JobScorePresenter::fitDetailsFields($job, 5);

        $this->assertSame(75, $fields['fit_details']['signals']['required_skills']['score']);
        $this->assertSame(['Laravel'], $fields['fit_details']['signals']['required_skills']['evidence']['matched_skills']);
        $this->assertEquals(1.0, $fields['fit_details']['confidence']);
        $this->assertSame('fit-v1', $fields['fit_details']['fit_version']);
    }

    #[Test]
    public function fit_details_are_null_when_no_analysis_exists(): void
    {
        $job = Job::factory()->make();
        $job->setRelation('analyses', collect());

        $fields = JobScorePresenter::fitDetailsFields($job, 5);

        $this->assertNull($fields['fit_details']);
    }
}
