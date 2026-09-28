<?php

declare(strict_types=1);

namespace Tests\Unit\Job;

use App\Enums\ExperienceLevel;
use App\Services\Job\ExperienceLevelInferenceService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExperienceLevelInferenceServiceTest extends TestCase
{
    private ExperienceLevelInferenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExperienceLevelInferenceService;
    }

    #[Test]
    #[DataProvider('highConfidenceTitles')]
    public function infers_high_confidence_titles(string $title, ExperienceLevel $expected): void
    {
        $this->assertSame($expected, $this->service->inferFromTitle($title));
    }

    /**
     * @return array<string, array{0: string, 1: ExperienceLevel}>
     */
    public static function highConfidenceTitles(): array
    {
        return [
            'english intern' => ['Software Engineering Intern', ExperienceLevel::Intern],
            'stajyer' => ['Stajyer Yazılım Geliştirici', ExperienceLevel::Intern],
            'staj' => ['Yazılım Geliştirme Staj Programı', ExperienceLevel::Intern],
            'junior' => ['Junior Software Developer', ExperienceLevel::Entry],
            'jr' => ['Jr. Backend Engineer', ExperienceLevel::Entry],
            'entry-level' => ['Entry-Level Frontend Developer', ExperienceLevel::Entry],
            'yeni mezun' => ['Yeni Mezun Programı', ExperienceLevel::Entry],
            'trainee' => ['IT Trainee', ExperienceLevel::Entry],
            'mid-level' => ['Mid-Level Backend Developer', ExperienceLevel::Mid],
            'intermediate' => ['Intermediate QA Engineer', ExperienceLevel::Mid],
            'senior' => ['Senior Software Engineer', ExperienceLevel::Senior],
            'sr' => ['Sr. Data Engineer', ExperienceLevel::Senior],
            'kidemli' => ['Kıdemli Yazılım Mühendisi', ExperienceLevel::Senior],
            'lead' => ['Lead Backend Engineer', ExperienceLevel::Lead],
            'principal' => ['Principal Software Engineer', ExperienceLevel::Lead],
            'staff' => ['Staff Engineer', ExperienceLevel::Lead],
            'engineering manager' => ['Engineering Manager', ExperienceLevel::Lead],
            'head of' => ['Head of Engineering', ExperienceLevel::Lead],
            'director' => ['Director of Product', ExperienceLevel::Executive],
            'direktor' => ['Teknoloji Direktörü', ExperienceLevel::Executive],
        ];
    }

    #[Test]
    #[DataProvider('unknownTitles')]
    public function leaves_generic_titles_unknown(string $title): void
    {
        $this->assertNull($this->service->inferFromTitle($title));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unknownTitles(): array
    {
        return [
            'software engineer' => ['Software Engineer'],
            'yazilim uzman' => ['Yazılım Geliştirme Uzmanı'],
            'backend' => ['Backend Developer'],
            'frontend' => ['Frontend Engineer'],
            'fullstack' => ['Full Stack Developer'],
            'qa without level' => ['Quality Assurance Engineer'],
            'empty' => [''],
            'javascript not junior' => ['JavaScript Developer'],
            'user not sr' => ['User Researcher'],
        ];
    }

    #[Test]
    public function junior_wins_over_manager_when_both_present(): void
    {
        $this->assertSame(
            ExperienceLevel::Entry,
            $this->service->inferFromTitle('Junior Engineering Manager'),
        );
    }

    #[Test]
    public function intern_wins_over_senior_when_both_present(): void
    {
        $this->assertSame(
            ExperienceLevel::Intern,
            $this->service->inferFromTitle('Senior Internship Program'),
        );
    }
}
