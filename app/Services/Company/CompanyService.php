<?php

declare(strict_types=1);

namespace App\Services\Company;

use App\Enums\CompanyVerificationStatus;
use App\Models\Company;
use App\Models\User;
use App\Support\ResolvesCompany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyService
{
    use ResolvesCompany;

    /** @var list<string> */
    private const UPDATABLE_FIELDS = [
        'name',
        'website',
        'industry',
        'company_size',
        'founded_year',
        'description',
        'city',
        'country',
        'tax_number',
        'social_links',
        'contact_email',
        'contact_phone',
    ];

    public function getForUser(User $user): Company
    {
        return $this->resolveCompany($user);
    }

    public function getPublicBySlug(string $slug): Company
    {
        $company = Company::query()->where('slug', $slug)->first();

        if ($company === null) {
            abort(404, 'Company not found.');
        }

        return $company;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateProfile(User $user, array $payload): Company
    {
        $company = $this->resolveCompany($user);
        $company->fill(Arr::only($payload, self::UPDATABLE_FIELDS));

        if (array_key_exists('name', $payload) && filled($payload['name'])) {
            $company->slug = $this->generateUniqueSlug((string) $payload['name'], $company->id);
        }

        $company->save();

        return $company->fresh();
    }

    public function uploadLogo(User $user, UploadedFile $file): Company
    {
        $company = $this->resolveCompany($user);
        $previousPath = $company->logo_path;

        $storedPath = $file->store(
            (string) config('company.logo.storage_path'),
            (string) config('company.logo.storage_disk'),
        );

        $company->logo_path = $storedPath;
        $company->save();

        $this->deleteStoredFile($previousPath);

        return $company->fresh();
    }

    public function deleteLogo(User $user): Company
    {
        $company = $this->resolveCompany($user);
        $previousPath = $company->logo_path;

        $company->logo_path = null;
        $company->save();

        $this->deleteStoredFile($previousPath);

        return $company->fresh();
    }

    public function requestVerification(User $user): Company
    {
        $company = $this->resolveCompany($user);

        if (in_array($company->verification_status, [
            CompanyVerificationStatus::Pending,
            CompanyVerificationStatus::Verified,
        ], true)) {
            throw ValidationException::withMessages([
                'verification_status' => ['Verification has already been requested or completed.'],
            ]);
        }

        $company->verification_status = CompanyVerificationStatus::Pending;
        $company->save();

        return $company->fresh();
    }

    private function generateUniqueSlug(string $name, int $ignoreCompanyId): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $suffix = 1;

        while (
            Company::query()
                ->where('slug', $slug)
                ->where('id', '!=', $ignoreCompanyId)
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function deleteStoredFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $disk = Storage::disk((string) config('company.logo.storage_disk'));

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
