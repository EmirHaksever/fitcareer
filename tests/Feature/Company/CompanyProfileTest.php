<?php

namespace Tests\Feature\Company;

use App\Enums\CompanySize;
use App\Enums\CompanyVerificationStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
{
    use CreatesCompanyUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    #[Test]
    public function company_user_can_view_own_profile(): void
    {
        [, $company, $token] = $this->createCompanyActor();

        $this->withToken($token)
            ->getJson('/api/v1/company/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $company->id)
            ->assertJsonPath('data.name', $company->name);
    }

    #[Test]
    public function company_user_can_update_profile_and_regenerate_slug_from_name(): void
    {
        [, $company, $token] = $this->createCompanyActor();

        $this->withToken($token)
            ->putJson('/api/v1/company/profile', [
                'name' => 'Acme Corporation',
                'website' => 'https://acme.example',
                'industry' => 'Software',
                'company_size' => CompanySize::ElevenToFifty->value,
                'description' => 'We build products.',
                'city' => 'Istanbul',
                'country' => 'Turkey',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Acme Corporation')
            ->assertJsonPath('data.slug', 'acme-corporation');

        $company->refresh();
        $this->assertSame('acme-corporation', $company->slug);
        $this->assertFalse($company->is_verified);
    }

    #[Test]
    public function protected_fields_cannot_be_mass_assigned(): void
    {
        [, $company, $token] = $this->createCompanyActor();

        $this->withToken($token)
            ->putJson('/api/v1/company/profile', [
                'name' => 'Updated Name',
                'user_id' => 999,
                'slug' => 'hijacked-slug',
                'is_verified' => true,
                'verification_status' => CompanyVerificationStatus::Verified->value,
                'trust_score' => 99,
                'logo_path' => 'fake/logo.png',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'user_id',
                'slug',
                'is_verified',
                'verification_status',
                'trust_score',
                'logo_path',
            ]);

        $company->refresh();
        $this->assertFalse($company->is_verified);
        $this->assertSame(CompanyVerificationStatus::Unverified, $company->verification_status);
        $this->assertNull($company->trust_score);
    }

    #[Test]
    public function company_user_can_upload_and_delete_logo(): void
    {
        [, $company, $token] = $this->createCompanyActor();

        $file = UploadedFile::fake()->image('logo.png');

        $this->withToken($token)
            ->postJson('/api/v1/company/profile/logo', ['logo' => $file])
            ->assertOk();

        $storedPath = $company->fresh()->logo_path;
        $this->assertNotNull($storedPath);
        Storage::disk('local')->assertExists($storedPath);

        $this->withToken($token)
            ->deleteJson('/api/v1/company/profile/logo')
            ->assertOk();

        Storage::disk('local')->assertMissing($storedPath);
        $this->assertNull($company->fresh()->logo_path);
    }

    #[Test]
    public function public_company_endpoint_hides_sensitive_fields(): void
    {
        [, $company] = $this->createCompanyActor();
        $company->update([
            'tax_number' => '1234567890',
            'contact_email' => 'hr@acme.example',
            'contact_phone' => '+905551112233',
        ]);

        $this->getJson('/api/v1/companies/'.$company->slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $company->slug)
            ->assertJsonMissing(['tax_number'])
            ->assertJsonMissing(['contact_email'])
            ->assertJsonMissing(['contact_phone']);
    }

    #[Test]
    public function validation_errors_use_standard_api_format(): void
    {
        [, , $token] = $this->createCompanyActor();

        $this->withToken($token)
            ->putJson('/api/v1/company/profile', [
                'company_size' => 'invalid-size',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['company_size']]);
    }
}
