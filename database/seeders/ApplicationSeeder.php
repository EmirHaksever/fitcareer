<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\JobStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Job;
use App\Models\User;
use App\Services\Application\ApplicationService;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

/**
 * Creates applications by driving the real ApplicationService, so every
 * application goes through the actual state machine (ApplicationService::
 * ALLOWED_TRANSITIONS) instead of writing an arbitrary status straight to
 * the database. This also means each transition produces a real
 * ApplicationStatusHistory row and an in-app Notification, exactly like a
 * real user/company action would.
 */
class ApplicationSeeder extends Seeder
{
    /**
     * A handful of realistic status "paths" an application can take,
     * expressed as the sequence of transitions away from "submitted".
     * Every step here must be a legal transition per ApplicationService.
     *
     * @var list<list<string>>
     */
    private const PATHS = [
        [],
        ['under_review'],
        ['under_review', 'shortlisted'],
        ['under_review', 'shortlisted', 'interview'],
        ['under_review', 'shortlisted', 'interview', 'offered'],
        ['under_review', 'rejected'],
        ['rejected'],
        ['under_review', 'shortlisted', 'rejected'],
        ['withdrawn'],
    ];

    /** @var array<string, string> */
    private const NOTES = [
        'under_review' => 'Başvuru İK tarafından inceleniyor.',
        'shortlisted' => 'Aday ön elemeyi geçti, mülakat süreci planlanıyor.',
        'interview' => 'Aday mülakata davet edildi.',
        'offered' => 'Adaya teklif sunuldu.',
        'rejected' => 'Aday, pozisyon için uygun bulunmadı.',
        'withdrawn' => 'Aday başvurusunu geri çekti.',
    ];

    public function __construct(
        private readonly ApplicationService $applicationService,
    ) {}

    public function run(): void
    {
        $jobs = Job::query()
            ->where('status', JobStatus::Published)
            ->with(['company.user', 'skills'])
            ->get();

        if ($jobs->isEmpty()) {
            return;
        }

        $candidates = User::query()
            ->where('role', UserRole::Candidate)
            ->with('candidateProfile')
            ->get()
            ->filter(fn (User $user): bool => $user->candidateProfile !== null);

        // Make sure the demo candidate ends up with a clearly "successful"
        // application against the demo company, for a nice live-demo moment.
        // We pick the demo company job whose required skills overlap the
        // most with the candidate's own skills, so the resulting Fit Score
        // (computed by the real FitScoreCalculator) is genuinely high rather
        // than a coincidence.
        $demoCandidate = $candidates->firstWhere('email', 'demo.aday@fitcareer.test');
        $demoCompanyJobs = $jobs->filter(fn (Job $job): bool => $job->company?->user?->email === 'demo.sirket@fitcareer.test');
        $demoJob = $this->bestMatchingJob($demoCandidate, $demoCompanyJobs);

        if ($demoCandidate !== null && $demoJob !== null) {
            $this->applyAndProgress($demoCandidate, $demoJob, ['under_review', 'shortlisted', 'interview', 'offered']);
        }

        foreach ($candidates as $candidate) {
            $appliedJobIds = $candidate->candidateProfile?->applications()->pluck('job_id')->all() ?? [];
            $availableJobs = $jobs->reject(fn (Job $job): bool => in_array($job->id, $appliedJobIds, true));

            if ($availableJobs->isEmpty()) {
                continue;
            }

            $appCount = min(random_int(2, 4), $availableJobs->count());

            foreach ($availableJobs->shuffle()->take($appCount) as $job) {
                $path = self::PATHS[array_rand(self::PATHS)];
                $this->applyAndProgress($candidate, $job, $path);
            }
        }
    }

    /**
     * @param  list<string>  $path
     */
    private function applyAndProgress(User $candidate, Job $job, array $path): void
    {
        try {
            $application = $this->applicationService->submit($candidate, ['job_id' => $job->id]);
        } catch (ValidationException) {
            return;
        }

        $companyActor = $job->company?->user;

        foreach ($path as $statusValue) {
            $status = ApplicationStatus::from($statusValue);
            $actor = $status === ApplicationStatus::Withdrawn ? $candidate : $companyActor;

            try {
                $this->applicationService->transitionStatus(
                    $application->id,
                    $status,
                    $actor,
                    self::NOTES[$statusValue] ?? null,
                );
            } catch (InvalidStatusTransitionException) {
                break;
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Job>  $jobs
     */
    private function bestMatchingJob(?User $candidate, $jobs): ?Job
    {
        if ($candidate === null || $jobs->isEmpty()) {
            return $jobs->first();
        }

        $candidateSkillIds = $candidate->candidateProfile?->skills()->pluck('skills.id')->all() ?? [];

        if ($candidateSkillIds === []) {
            return $jobs->first();
        }

        return $jobs
            ->sortByDesc(fn (Job $job): int => $job->skills->pluck('id')->intersect($candidateSkillIds)->count())
            ->first();
    }
}
