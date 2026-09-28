<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Scraper;

use App\Enums\TurkeyLocationCategory;
use App\Enums\WorkType;
use App\Services\Scraper\DTO\LocationInput;
use App\Services\Scraper\LocationClassificationService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocationClassificationServiceTest extends TestCase
{
    private LocationClassificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LocationClassificationService::class);
    }

    #[DataProvider('turkeyRelevantLocationProvider')]
    public function test_turkey_relevant_locations_are_accepted(
        ?string $city,
        ?string $country,
        ?WorkType $workType,
        array $rawStrings,
        TurkeyLocationCategory $expectedCategory,
    ): void {
        $result = $this->service->classify(
            LocationInput::fromSignals($city, $country, $workType, $rawStrings),
        );

        $this->assertTrue($result->isTurkeyRelevant, "Expected Turkey-relevant for: {$city}, {$country}");
        $this->assertSame($expectedCategory, $result->category);
    }

    public static function turkeyRelevantLocationProvider(): array
    {
        return [
            'Istanbul' => ['Istanbul', null, WorkType::Onsite, ['Istanbul'], TurkeyLocationCategory::Istanbul],
            'İstanbul' => ['İstanbul', null, WorkType::Onsite, ['İstanbul'], TurkeyLocationCategory::Istanbul],
            'Istanbul Turkey' => ['Istanbul', 'Turkey', WorkType::Onsite, ['Istanbul, Turkey'], TurkeyLocationCategory::Istanbul],
            'Turkey country' => [null, 'Turkey', WorkType::Onsite, ['Turkey'], TurkeyLocationCategory::OtherTurkey],
            'Türkiye country' => [null, 'Türkiye', WorkType::Onsite, ['Türkiye'], TurkeyLocationCategory::OtherTurkey],
            'Ankara' => ['Ankara', 'Turkey', WorkType::Onsite, ['Ankara, Turkey'], TurkeyLocationCategory::OtherTurkey],
            'Izmir' => ['İzmir', 'Turkey', WorkType::Onsite, ['İzmir, Turkey'], TurkeyLocationCategory::OtherTurkey],
            'Remote Turkey' => [null, 'Turkey', WorkType::Remote, ['Remote - Turkey'], TurkeyLocationCategory::RemoteTurkey],
            'Remote Türkiye comma' => [null, 'Türkiye', WorkType::Remote, ['Remote, Türkiye'], TurkeyLocationCategory::RemoteTurkey],
            'Maslak Istanbul' => ['Maslak', 'Istanbul', WorkType::Hybrid, ['Maslak / Istanbul'], TurkeyLocationCategory::Istanbul],
            'Türkiye Remote' => [null, 'Türkiye', WorkType::Remote, ['Türkiye - Remote'], TurkeyLocationCategory::RemoteTurkey],
            'Multiple locations with Istanbul' => ['Istanbul', null, WorkType::Hybrid, ['London | Istanbul | Berlin'], TurkeyLocationCategory::Istanbul],
        ];
    }

    #[DataProvider('notTurkeyRelevantLocationProvider')]
    public function test_non_turkey_locations_are_rejected(
        ?string $city,
        ?string $country,
        ?WorkType $workType,
        array $rawStrings,
    ): void {
        $result = $this->service->classify(
            LocationInput::fromSignals($city, $country, $workType, $rawStrings),
        );

        $this->assertFalse($result->isTurkeyRelevant);
    }

    public static function notTurkeyRelevantLocationProvider(): array
    {
        return [
            'EMEA' => [null, 'EMEA', WorkType::Remote, ['EMEA']],
            'Europe' => [null, 'Europe', WorkType::Remote, ['Europe']],
            'Remote Worldwide' => [null, null, WorkType::Remote, ['Remote Worldwide']],
            'London only' => ['London', 'United Kingdom', WorkType::Onsite, ['London, UK']],
            'NULL location remote' => [null, null, WorkType::Remote, []],
            'NULL location onsite' => [null, null, WorkType::Onsite, []],
            'Remote only signal' => [null, null, WorkType::Remote, ['Remote']],
        ];
    }
}
