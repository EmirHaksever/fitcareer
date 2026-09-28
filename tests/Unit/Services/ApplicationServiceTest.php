<?php

namespace Tests\Unit\Services;

use App\Enums\ApplicationStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Application;
use App\Services\Application\ApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function transition_status_records_history_and_updates_application(): void
    {
        $application = Application::factory()->create([
            'status' => ApplicationStatus::Submitted,
        ]);

        $updated = app(ApplicationService::class)->transitionStatus(
            $application->id,
            ApplicationStatus::UnderReview,
        );

        $this->assertSame(ApplicationStatus::UnderReview, $updated->status);
        $this->assertDatabaseHas('application_status_history', [
            'application_id' => $application->id,
            'from_status' => ApplicationStatus::Submitted->value,
            'to_status' => ApplicationStatus::UnderReview->value,
        ]);
    }

    #[Test]
    public function transition_status_rejects_invalid_transition(): void
    {
        $application = Application::factory()->create([
            'status' => ApplicationStatus::Rejected,
        ]);

        $this->expectException(InvalidStatusTransitionException::class);

        app(ApplicationService::class)->transitionStatus(
            $application->id,
            ApplicationStatus::Interview,
        );
    }

    #[Test]
    public function is_transition_allowed_matches_matrix(): void
    {
        $this->assertTrue(ApplicationService::isTransitionAllowed(
            ApplicationStatus::Submitted,
            ApplicationStatus::UnderReview,
        ));

        $this->assertFalse(ApplicationService::isTransitionAllowed(
            ApplicationStatus::Withdrawn,
            ApplicationStatus::Submitted,
        ));
    }
}
