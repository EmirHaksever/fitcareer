<?php

namespace Tests\Feature\Admin;

use App\Enums\CompanyVerificationStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyVerificationAdminTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminToken(): string
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        return $admin->createToken('api-access')->plainTextToken;
    }

    #[Test]
    public function admin_can_list_pending_companies(): void
    {
        $token = $this->createAdminToken();

        Company::factory()->create(['verification_status' => CompanyVerificationStatus::Pending]);
        Company::factory()->create(['verification_status' => CompanyVerificationStatus::Verified]);

        $this->withToken($token)
            ->getJson('/api/v1/admin/companies/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    #[Test]
    public function admin_can_approve_pending_company(): void
    {
        $token = $this->createAdminToken();
        $company = Company::factory()->create(['verification_status' => CompanyVerificationStatus::Pending]);

        $this->withToken($token)
            ->postJson('/api/v1/admin/companies/'.$company->id.'/verify', ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.verification_status', CompanyVerificationStatus::Verified->value)
            ->assertJsonPath('data.is_verified', true);
    }

    #[Test]
    public function admin_can_reject_pending_company(): void
    {
        $token = $this->createAdminToken();
        $company = Company::factory()->create(['verification_status' => CompanyVerificationStatus::Pending]);

        $this->withToken($token)
            ->postJson('/api/v1/admin/companies/'.$company->id.'/verify', ['action' => 'reject'])
            ->assertOk()
            ->assertJsonPath('data.verification_status', CompanyVerificationStatus::Rejected->value)
            ->assertJsonPath('data.is_verified', false);
    }

    #[Test]
    public function non_admin_cannot_access_admin_endpoints(): void
    {
        $company = User::factory()->company()->create();
        $token = $company->createToken('api-access')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/admin/companies/pending')
            ->assertForbidden();
    }
}
