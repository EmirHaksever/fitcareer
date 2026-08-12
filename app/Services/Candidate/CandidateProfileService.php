<?php

declare(strict_types=1);

namespace App\Services\Candidate;

use App\Models\CandidateCertification;
use App\Models\CandidateEducation;
use App\Models\CandidateExperience;
use App\Models\CandidateProfile;
use App\Models\CandidateProject;
use App\Models\CandidateSkill;
use App\Models\Skill;
use App\Models\User;
use App\Services\AI\CvExtractionPipeline;
use App\Support\ResolvesCandidateProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CandidateProfileService
{
    use ResolvesCandidateProfile;

    /** @var list<string> */
    private const PROFILE_RELATIONS = [
        'experiences',
        'educations',
        'certifications',
        'projects',
        'candidateSkills.skill',
    ];

    /** @var list<string> */
    private const UPDATABLE_PROFILE_FIELDS = [
        'headline',
        'summary',
        'city',
        'country',
        'open_to_work',
        'desired_position',
        'desired_salary_min',
        'desired_salary_max',
        'work_preference',
        'years_of_experience',
        'linkedin_url',
        'github_url',
        'portfolio_url',
    ];

    public function __construct(
        private readonly ProfileStrengthCalculator $strengthCalculator,
        private readonly CvParserService $cvParser,
        private readonly CvExtractionPipeline $cvExtractionPipeline,
    ) {}

    public function getForUser(User $user): CandidateProfile
    {
        $profile = $this->resolveCandidateProfile($user);
        $profile->load(self::PROFILE_RELATIONS);

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateProfile(User $user, array $payload): CandidateProfile
    {
        $profile = $this->resolveCandidateProfile($user);
        $profile->fill(Arr::only($payload, self::UPDATABLE_PROFILE_FIELDS));
        $profile->save();

        return $this->recalculateAndReturn($profile);
    }

    public function uploadProfilePhoto(User $user, UploadedFile $file): CandidateProfile
    {
        $profile = $this->resolveCandidateProfile($user);
        $previousPath = $profile->profile_photo_path;
        $storedPath = $file->store(
            (string) config('candidate.photo.storage_path'),
            (string) config('candidate.photo.storage_disk'),
        );

        $profile->profile_photo_path = $storedPath;
        $profile->save();

        $this->deleteStoredFile($previousPath, (string) config('candidate.photo.storage_disk'));

        return $this->recalculateAndReturn($profile);
    }

    public function deleteProfilePhoto(User $user): CandidateProfile
    {
        $profile = $this->resolveCandidateProfile($user);
        $previousPath = $profile->profile_photo_path;

        $profile->profile_photo_path = null;
        $profile->save();

        $this->deleteStoredFile($previousPath, (string) config('candidate.photo.storage_disk'));

        return $this->recalculateAndReturn($profile);
    }

    public function uploadCv(User $user, UploadedFile $file): CandidateProfile
    {
        $profile = $this->resolveCandidateProfile($user);
        $previousPath = $profile->cv_file_path;

        $storedPath = $file->store(
            (string) config('candidate.cv.storage_path'),
            (string) config('candidate.cv.storage_disk'),
        );

        $absolutePath = Storage::disk((string) config('candidate.cv.storage_disk'))->path($storedPath);
        $parsedData = $this->cvParser->parse($absolutePath, $file->getClientOriginalName());

        if ($this->cvExtractionPipeline->isEnabled()) {
            try {
                $parsedData['ai_extraction'] = $this->cvExtractionPipeline->run($profile, $parsedData['text']);
            } catch (\Throwable $exception) {
                $parsedData['ai_extraction'] = $this->cvExtractionPipeline->buildFailureMetadata($exception);
            }
        }

        $profile->cv_file_path = $storedPath;
        $profile->cv_parsed_data = $parsedData;
        $profile->save();

        $this->deleteStoredFile($previousPath);

        return $this->recalculateAndReturn($profile);
    }

    public function deleteCv(User $user): CandidateProfile
    {
        $profile = $this->resolveCandidateProfile($user);
        $previousPath = $profile->cv_file_path;

        $profile->cv_file_path = null;
        $profile->cv_parsed_data = null;
        $profile->save();

        $this->deleteStoredFile($previousPath);

        return $this->recalculateAndReturn($profile);
    }

    /**
     * @return Collection<int, CandidateExperience>
     */
    public function listExperiences(User $user): Collection
    {
        return $this->resolveCandidateProfile($user)->experiences()->orderByDesc('start_date')->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createExperience(User $user, array $payload): CandidateExperience
    {
        $profile = $this->resolveCandidateProfile($user);

        $experience = $profile->experiences()->create($payload);
        $this->recalculateStrength($profile);

        return $experience;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateExperience(User $user, int $experienceId, array $payload): CandidateExperience
    {
        $profile = $this->resolveCandidateProfile($user);
        $experience = $this->findOwnedResource($profile, 'experiences', $experienceId, CandidateExperience::class);
        $experience->fill($payload);
        $experience->save();

        $this->recalculateStrength($profile);

        return $experience;
    }

    public function deleteExperience(User $user, int $experienceId): void
    {
        $profile = $this->resolveCandidateProfile($user);
        $experience = $this->findOwnedResource($profile, 'experiences', $experienceId, CandidateExperience::class);
        $experience->delete();

        $this->recalculateStrength($profile);
    }

    /**
     * @return Collection<int, CandidateEducation>
     */
    public function listEducations(User $user): Collection
    {
        return $this->resolveCandidateProfile($user)->educations()->orderByDesc('start_date')->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createEducation(User $user, array $payload): CandidateEducation
    {
        $profile = $this->resolveCandidateProfile($user);
        $education = $profile->educations()->create($payload);
        $this->recalculateStrength($profile);

        return $education;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateEducation(User $user, int $educationId, array $payload): CandidateEducation
    {
        $profile = $this->resolveCandidateProfile($user);
        $education = $this->findOwnedResource($profile, 'educations', $educationId, CandidateEducation::class);
        $education->fill($payload);
        $education->save();

        $this->recalculateStrength($profile);

        return $education;
    }

    public function deleteEducation(User $user, int $educationId): void
    {
        $profile = $this->resolveCandidateProfile($user);
        $education = $this->findOwnedResource($profile, 'educations', $educationId, CandidateEducation::class);
        $education->delete();

        $this->recalculateStrength($profile);
    }

    /**
     * @return Collection<int, CandidateCertification>
     */
    public function listCertifications(User $user): Collection
    {
        return $this->resolveCandidateProfile($user)->certifications()->orderByDesc('issue_date')->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createCertification(User $user, array $payload): CandidateCertification
    {
        $profile = $this->resolveCandidateProfile($user);
        $certification = $profile->certifications()->create($payload);

        $this->recalculateStrength($profile);

        return $certification;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateCertification(User $user, int $certificationId, array $payload): CandidateCertification
    {
        $profile = $this->resolveCandidateProfile($user);
        $certification = $this->findOwnedResource($profile, 'certifications', $certificationId, CandidateCertification::class);
        $certification->fill($payload);
        $certification->save();

        $this->recalculateStrength($profile);

        return $certification;
    }

    public function deleteCertification(User $user, int $certificationId): void
    {
        $profile = $this->resolveCandidateProfile($user);
        $certification = $this->findOwnedResource($profile, 'certifications', $certificationId, CandidateCertification::class);
        $certification->delete();

        $this->recalculateStrength($profile);
    }

    /**
     * @return Collection<int, CandidateProject>
     */
    public function listProjects(User $user): Collection
    {
        return $this->resolveCandidateProfile($user)->projects()->orderByDesc('start_date')->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createProject(User $user, array $payload): CandidateProject
    {
        $profile = $this->resolveCandidateProfile($user);
        $project = $profile->projects()->create($payload);
        $this->recalculateStrength($profile);

        return $project;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateProject(User $user, int $projectId, array $payload): CandidateProject
    {
        $profile = $this->resolveCandidateProfile($user);
        $project = $this->findOwnedResource($profile, 'projects', $projectId, CandidateProject::class);
        $project->fill($payload);
        $project->save();

        $this->recalculateStrength($profile);

        return $project;
    }

    public function deleteProject(User $user, int $projectId): void
    {
        $profile = $this->resolveCandidateProfile($user);
        $project = $this->findOwnedResource($profile, 'projects', $projectId, CandidateProject::class);
        $project->delete();

        $this->recalculateStrength($profile);
    }

    /**
     * @return Collection<int, Skill>
     */
    public function listSkills(User $user): Collection
    {
        return $this->resolveCandidateProfile($user)
            ->candidateSkills()
            ->with('skill')
            ->get()
            ->sortBy(static fn (CandidateSkill $candidateSkill): string => $candidateSkill->skill->name)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function attachSkill(User $user, array $payload): CandidateSkill
    {
        $profile = $this->resolveCandidateProfile($user);

        if (! Skill::query()->whereKey($payload['skill_id'])->exists()) {
            throw ValidationException::withMessages([
                'skill_id' => ['The selected skill is invalid.'],
            ]);
        }

        if ($profile->candidateSkills()->where('skill_id', $payload['skill_id'])->exists()) {
            throw ValidationException::withMessages([
                'skill_id' => ['This skill has already been added to the profile.'],
            ]);
        }

        $candidateSkill = $profile->candidateSkills()->create([
            'skill_id' => $payload['skill_id'],
            'proficiency_level' => $payload['proficiency_level'] ?? null,
            'years_of_experience' => $payload['years_of_experience'] ?? null,
        ]);

        $this->recalculateStrength($profile);

        return $candidateSkill->load('skill');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateSkill(User $user, int $candidateSkillId, array $payload): CandidateSkill
    {
        $profile = $this->resolveCandidateProfile($user);
        $candidateSkill = $this->findOwnedResource($profile, 'candidateSkills', $candidateSkillId, CandidateSkill::class);
        $candidateSkill->fill(Arr::only($payload, ['proficiency_level', 'years_of_experience']));
        $candidateSkill->save();

        $this->recalculateStrength($profile);

        return $candidateSkill->load('skill');
    }

    public function detachSkill(User $user, int $candidateSkillId): void
    {
        $profile = $this->resolveCandidateProfile($user);
        $candidateSkill = $this->findOwnedResource($profile, 'candidateSkills', $candidateSkillId, CandidateSkill::class);
        $candidateSkill->delete();

        $this->recalculateStrength($profile);
    }

    /**
     * @return Collection<int, Skill>
     */
    public function searchSkills(?string $query, int $limit = 20): Collection
    {
        $builder = Skill::query()->orderBy('name');

        if ($query !== null && $query !== '') {
            $builder->where(function ($inner) use ($query): void {
                $inner->where('name', 'like', '%'.$query.'%')
                    ->orWhere('slug', 'like', '%'.strtolower($query).'%');
            });
        }

        return $builder->limit($limit)->get();
    }

    private function recalculateAndReturn(CandidateProfile $profile): CandidateProfile
    {
        $this->recalculateStrength($profile);

        return $profile->fresh(self::PROFILE_RELATIONS);
    }

    private function recalculateStrength(CandidateProfile $profile): void
    {
        $profile->loadCount(['experiences', 'educations', 'skills']);
        $profile->profile_strength_score = $this->strengthCalculator->calculate($profile);
        $profile->save();
    }

    private function deleteStoredFile(?string $path, ?string $diskName = null): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $disk = Storage::disk($diskName ?? (string) config('candidate.cv.storage_disk'));

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
