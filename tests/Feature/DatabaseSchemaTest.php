<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_planned_domain_and_infrastructure_tables_exist(): void
    {
        $tables = [
            'users',
            'companies',
            'candidate_profiles',
            'skills',
            'candidate_skills',
            'candidate_experiences',
            'candidate_educations',
            'candidate_certifications',
            'candidate_projects',
            'job_sources',
            'jobs',
            'job_skills',
            'job_import_runs',
            'job_refresh_requests',
            'ai_analyses',
            'applications',
            'application_status_history',
            'saved_jobs',
            'saved_companies',
            'job_reports',
            'notifications',
            'user_settings',
            'personal_access_tokens',
            'queue_jobs',
            'job_batches',
            'failed_jobs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_jobs_table_contains_scraping_trust_and_provenance_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('jobs', [
            'company_id',
            'job_source_id',
            'source_company_name',
            'external_id',
            'content_hash',
            'trust_score',
            'trust_label',
            'trust_analysis_status',
            'last_scraped_at',
            'scrape_status',
            'scrape_error',
        ]));
    }

    public function test_ai_analysis_versioning_and_application_snapshots_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('ai_analyses', [
            'analysis_version',
            'prompt_version',
            'ai_model',
            'status',
            'is_latest',
            'analyzed_at',
            'raw_response',
        ]));

        $this->assertTrue(Schema::hasColumns('applications', [
            'match_score',
            'trust_score',
            'status_updated_at',
        ]));
    }
}
