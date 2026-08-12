<?php

namespace Tests\Feature\Company;

use App\Enums\CompanyVerificationStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyVerificationTest extends TestCase
{
    use CreatesCompanyUsers;

    #[Test]
    public function company_can_request_verification_when_unverified(): void
    {
        [, $company, $token] = $this->createCompanyActor();

        $this->withToken($token)
            ->postJson('/api/v1/company/verification/request')
            ->assertOk()
            ->assertJsonPath('data.verification_status', CompanyVerificationStatus::Pending->value);

        $this->assertSame(CompanyVerificationStatus::Pending, $company->fresh()->verification_status);
        $this->assertFalse($company->fresh()->is_verified);
    }

    #[Test]
    public function company_can_request_verification_again_after_rejection(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $company->update(['verification_status' => CompanyVerificationStatus::Rejected]);

        $this->withToken($token)
            ->postJson('/api/v1/company/verification/request')
            ->assertOk()
            ->assertJsonPath('data.verification_status', CompanyVerificationStatus::Pending->value);
    }

    #[Test]
    public function duplicate_verification_request_is_rejected(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $company->update(['verification_status' => CompanyVerificationStatus::Pending]);

        $this->withToken($token)
            ->postJson('/api/v1/company/verification/request')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verification_status']);
    }

    #[Test]
    public function verified_company_cannot_request_verification_again(): void
    {
        [, $company, $token] = $this->createCompanyActor();
        $company->update([
            'verification_status' => CompanyVerificationStatus::Verified,
            'is_verified' => true,
        ]);

        $this->withToken($token)
            ->postJson('/api/v1/company/verification/request')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['verification_status']);
    }
}
