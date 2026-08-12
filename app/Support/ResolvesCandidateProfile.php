<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait ResolvesCandidateProfile
{
    protected function resolveCandidateProfile(User $user): CandidateProfile
    {
        $profile = $user->candidateProfile;

        if ($profile === null) {
            abort(404, 'Candidate profile not found.');
        }

        return $profile;
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @return TModel
     */
    protected function findOwnedResource(
        CandidateProfile $profile,
        string $relation,
        int $resourceId,
        string $modelClass,
    ): Model {
        /** @var TModel|null $resource */
        $resource = $profile->{$relation}()->whereKey($resourceId)->first();

        if ($resource === null) {
            abort(404, class_basename($modelClass).' not found.');
        }

        return $resource;
    }
}
