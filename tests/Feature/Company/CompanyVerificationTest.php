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

    #[Test]
    public function artisan_approve_completes_pending_verification_atomically(): void
    {
        [, $company] = $this->createCompanyActor();
        $company->update(['verification_status' => CompanyVerificationStatus::Pending]);

        $this->artisan('company:verification', [
            'action' => 'approve',
            'company' => (string) $company->id,
        ])->assertSuccessful();

        $company->refresh();
        $this->assertSame(CompanyVerificationStatus::Verified, $company->verification_status);
        $this->assertTrue($company->is_verified);
    }

    #[Test]
    public function artisan_reject_keeps_is_verified_false(): void
    {
        [, $company] = $this->createCompanyActor();
        $company->update([
            'verification_status' => CompanyVerificationStatus::Pending,
            'slug' => 'pending-employer',
        ]);

        $this->artisan('company:verification', [
            'action' => 'reject',
            'company' => 'pending-employer',
        ])->assertSuccessful();

        $company->refresh();
        $this->assertSame(CompanyVerificationStatus::Rejected, $company->verification_status);
        $this->assertFalse($company->is_verified);
    }

    #[Test]
    public function artisan_rejects_unknown_company_safely(): void
    {
        $this->createCompanyActor();

        $this->artisan('company:verification', [
            'action' => 'approve',
            'company' => 'missing-company',
        ])->assertFailed();
    }

    #[Test]
    public function artisan_rejects_invalid_transition_from_unverified(): void
    {
        [, $company] = $this->createCompanyActor();

        $this->artisan('company:verification', [
            'action' => 'approve',
            'company' => (string) $company->id,
        ])->assertFailed();

        $company->refresh();
        $this->assertSame(CompanyVerificationStatus::Unverified, $company->verification_status);
        $this->assertFalse($company->is_verified);
    }

    #[Test]
    public function artisan_approval_does_not_affect_another_company(): void
    {
        [, $companyA] = $this->createCompanyActor();
        [, $companyB] = $this->createCompanyActor();
        $companyA->update(['verification_status' => CompanyVerificationStatus::Pending]);

        $this->artisan('company:verification', [
            'action' => 'approve',
            'company' => (string) $companyA->id,
        ])->assertSuccessful();

        $companyB->refresh();
        $this->assertSame(CompanyVerificationStatus::Unverified, $companyB->verification_status);
        $this->assertFalse($companyB->is_verified);
        $this->assertTrue($companyA->fresh()->is_verified);
    }
}
