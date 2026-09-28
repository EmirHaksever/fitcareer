<?php

namespace Tests\Unit\FitScore;

use App\Models\Job;
use App\Services\FitScore\FitScoreWeightResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FitScoreWeightResolverTest extends TestCase
{
    #[Test]
    public function it_returns_default_weights_when_job_has_no_custom_configuration(): void
    {
        $job = Job::factory()->make(['fit_score_weights' => null]);

        $resolution = app(FitScoreWeightResolver::class)->resolveForJob($job);

        $this->assertSame('default', $resolution['source']);
        $this->assertSame(config('fit_score.weights'), $resolution['weights']);
    }

    #[Test]
    public function it_returns_custom_weights_when_configured_on_job(): void
    {
        $custom = [
            'required_skills' => 40,
            'preferred_skills' => 10,
            'experience' => 25,
            'work_type' => 10,
            'location' => 5,
            'salary' => 10,
        ];
        $job = Job::factory()->make(['fit_score_weights' => $custom]);

        $resolution = app(FitScoreWeightResolver::class)->resolveForJob($job);

        $this->assertSame('custom', $resolution['source']);
        $this->assertSame($custom, $resolution['weights']);
    }
}
