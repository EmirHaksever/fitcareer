<?php

declare(strict_types=1);

namespace Tests\Feature\Job;

use App\Enums\TrustLabel;
use App\Models\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModerateTrustLabelMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scored_jobs_left_as_unrated_are_relabelled_from_their_score(): void
    {
        $moderate = Job::factory()->published()->create(['trust_score' => 60, 'trust_label' => TrustLabel::Unrated]);
        $verified = Job::factory()->published()->create(['trust_score' => 80, 'trust_label' => TrustLabel::Unrated]);
        $suspicious = Job::factory()->published()->create(['trust_score' => 35, 'trust_label' => TrustLabel::Unrated]);
        $lowTrust = Job::factory()->published()->create(['trust_score' => 10, 'trust_label' => TrustLabel::Unrated]);
        $notScored = Job::factory()->published()->create(['trust_score' => null, 'trust_label' => TrustLabel::Unrated]);

        $this->migration()->up();

        $this->assertSame(TrustLabel::Moderate, $moderate->fresh()->trust_label);
        $this->assertSame(TrustLabel::Verified, $verified->fresh()->trust_label);
        $this->assertSame(TrustLabel::Suspicious, $suspicious->fresh()->trust_label);
        $this->assertSame(TrustLabel::LowTrust, $lowTrust->fresh()->trust_label);
        $this->assertSame(TrustLabel::Unrated, $notScored->fresh()->trust_label);
    }

    public function test_down_folds_moderate_back_into_unrated(): void
    {
        $job = Job::factory()->published()->create(['trust_score' => 60, 'trust_label' => TrustLabel::Moderate]);

        $this->migration()->down();

        $this->assertSame(TrustLabel::Unrated, $job->fresh()->trust_label);

        $this->migration()->up();

        $this->assertSame(TrustLabel::Moderate, $job->fresh()->trust_label);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_27_000001_add_moderate_trust_label_to_jobs.php');
    }
}
