<?php

declare(strict_types=1);

namespace Tests\Feature\Candidate;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SkillCatalogLimitTest extends TestCase
{
    #[Test]
    public function skills_catalog_rejects_limit_above_fifty(): void
    {
        $this->getJson('/api/v1/skills?limit=100')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    }

    #[Test]
    public function skills_catalog_accepts_limit_of_fifty(): void
    {
        $this->getJson('/api/v1/skills?limit=50')
            ->assertOk();
    }
}
