<?php

namespace Tests\Feature\Company;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompanyAuthorizationTest extends TestCase
{
    use CreatesCompanyUsers;

    #[Test]
    public function guest_cannot_access_company_profile(): void
    {
        $this->getJson('/api/v1/company/profile')
            ->assertUnauthorized();
    }

    #[Test]
    public function candidate_cannot_access_company_profile(): void
    {
        $this->withToken($this->createCandidateActorToken())
            ->getJson('/api/v1/company/profile')
            ->assertForbidden();
    }

    #[Test]
    public function admin_cannot_access_company_profile_routes(): void
    {
        $this->withToken($this->createAdminActorToken())
            ->getJson('/api/v1/company/profile')
            ->assertForbidden();
    }
}
