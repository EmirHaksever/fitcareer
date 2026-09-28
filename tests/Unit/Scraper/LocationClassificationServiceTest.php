<?php

declare(strict_types=1);

namespace Tests\Unit\Scraper;

use App\Enums\TurkeyLocationCategory;
use App\Enums\WorkType;
use App\Services\Scraper\DTO\LocationInput;
use App\Services\Scraper\LocationClassificationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocationClassificationServiceTest extends TestCase
{
    private LocationClassificationService $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new LocationClassificationService;
    }

    #[Test]
    public function foreign_town_containing_a_turkish_city_name_stays_foreign(): void
    {
        // "Villa d'Agri" (Italy) contains "agri" (Ağrı) but the country is explicit.
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: "Villa d'Agri",
            country: 'Italy',
            workType: WorkType::Onsite,
        ));

        $this->assertFalse($result->isTurkeyRelevant);
        $this->assertSame('Italy', $result->country);
    }

    #[Test]
    public function exact_turkish_city_still_wins_over_a_foreign_country_label(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'Istanbul',
            country: 'Germany',
            workType: WorkType::Onsite,
        ));

        $this->assertTrue($result->isTurkeyRelevant);
    }

    #[Test]
    public function classifies_istanbul_turkey_as_turkey_relevant(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'Istanbul',
            country: 'Turkey',
            workType: WorkType::Onsite,
            rawLocationStrings: ['Istanbul, Turkey'],
        ));

        $this->assertTrue($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::Istanbul, $result->category);
        $this->assertSame('Istanbul', $result->city);
        $this->assertSame('Türkiye', $result->country);
    }

    #[Test]
    public function classifies_istanbul_turkiye_with_turkish_characters(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'İstanbul',
            country: 'Türkiye',
            workType: WorkType::Onsite,
            rawLocationStrings: ['İstanbul, Türkiye'],
        ));

        $this->assertTrue($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::Istanbul, $result->category);
        $this->assertSame('İstanbul', $result->city);
        $this->assertSame('Türkiye', $result->country);
    }

    #[Test]
    public function corrects_lever_city_country_swap_when_country_is_istanbul(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: null,
            country: 'Istanbul',
            workType: WorkType::Onsite,
            rawLocationStrings: ['Istanbul'],
        ));

        $this->assertTrue($result->isTurkeyRelevant);
        $this->assertSame('Istanbul', $result->city);
        $this->assertSame('Türkiye', $result->country);
    }

    #[Test]
    public function corrects_both_city_and_country_set_to_istanbul(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'Istanbul',
            country: 'Istanbul',
            workType: WorkType::Onsite,
            rawLocationStrings: ['Istanbul'],
        ));

        $this->assertTrue($result->isTurkeyRelevant);
        $this->assertSame('Istanbul', $result->city);
        $this->assertSame('Türkiye', $result->country);
    }

    #[Test]
    public function does_not_correct_conflicting_turkish_cities_in_city_and_country(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'Ankara',
            country: 'Istanbul',
            workType: WorkType::Onsite,
            rawLocationStrings: ['Ankara', 'Istanbul'],
        ));

        $this->assertTrue($result->isTurkeyRelevant);
        $this->assertSame('Ankara', $result->city);
        $this->assertSame('Istanbul', $result->country);
    }

    #[Test]
    public function leaves_istanbul_turkey_unchanged(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'Istanbul',
            country: 'Turkey',
            workType: WorkType::Onsite,
            rawLocationStrings: ['Istanbul, Turkey'],
        ));

        $this->assertSame('Istanbul', $result->city);
        $this->assertSame('Türkiye', $result->country);
    }

    #[Test]
    public function sets_country_for_greenhouse_city_only_istanbul(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'Istanbul',
            country: null,
            workType: WorkType::Onsite,
            rawLocationStrings: ['Istanbul'],
        ));

        $this->assertTrue($result->isTurkeyRelevant);
        $this->assertSame('Istanbul', $result->city);
        $this->assertSame('Türkiye', $result->country);
    }

    #[Test]
    public function leaves_london_uk_unchanged(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'London',
            country: 'UK',
            workType: WorkType::Onsite,
            rawLocationStrings: ['London, UK'],
        ));

        $this->assertFalse($result->isTurkeyRelevant);
        $this->assertSame('London', $result->city);
        $this->assertSame('UK', $result->country);
    }

    #[Test]
    public function classifies_remote_turkey_as_remote_tr(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: null,
            country: null,
            workType: WorkType::Remote,
            rawLocationStrings: ['Remote - Turkey'],
        ));

        $this->assertTrue($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::RemoteTurkey, $result->category);
    }

    #[Test]
    public function remotive_unknown_stays_unknown_without_evidence(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: null,
            country: null,
            workType: WorkType::Remote,
            rawLocationStrings: ['Remote'],
        ));

        $this->assertFalse($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::Unknown, $result->category);
        $this->assertNull($result->city);
        $this->assertNull($result->country);
    }

    #[Test]
    public function classifies_ankara_turkey_as_other_turkey(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'Ankara',
            country: 'Turkey',
            workType: WorkType::Onsite,
            rawLocationStrings: ['Ankara, Turkey'],
        ));

        $this->assertTrue($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::OtherTurkey, $result->category);
        $this->assertSame('Ankara', $result->city);
    }

    #[Test]
    #[DataProvider('explicitTurkeyRemoteProvider')]
    public function classifies_explicit_turkey_remote_patterns(string $rawLocation): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: null,
            country: null,
            workType: WorkType::Remote,
            rawLocationStrings: [$rawLocation],
        ));

        $this->assertTrue($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::RemoteTurkey, $result->category);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function explicitTurkeyRemoteProvider(): array
    {
        return [
            'remote dash turkey' => ['Remote - Turkey'],
            'remote dash turkiye' => ['Remote - Türkiye'],
            'turkey parentheses remote' => ['Turkey (Remote)'],
            'turkiye parentheses remote' => ['Türkiye (Remote)'],
        ];
    }

    #[Test]
    #[DataProvider('globalRemoteProvider')]
    public function classifies_global_remote_patterns_as_foreign(string $rawLocation): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: null,
            country: null,
            workType: WorkType::Remote,
            rawLocationStrings: [$rawLocation],
        ));

        $this->assertFalse($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::Foreign, $result->category);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function globalRemoteProvider(): array
    {
        return [
            'remote europe' => ['Remote - Europe'],
            'remote emea' => ['Remote - EMEA'],
            'remote worldwide' => ['Remote - Worldwide'],
        ];
    }

    #[Test]
    public function classifies_london_uk_as_foreign(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'London',
            country: 'UK',
            workType: WorkType::Onsite,
            rawLocationStrings: ['London, UK'],
        ));

        $this->assertFalse($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::Foreign, $result->category);
    }

    #[Test]
    public function classifies_berlin_germany_as_foreign(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'Berlin',
            country: 'Germany',
            workType: WorkType::Onsite,
            rawLocationStrings: ['Berlin, Germany'],
        ));

        $this->assertFalse($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::Foreign, $result->category);
    }

    #[Test]
    public function classifies_missing_location_as_unknown(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: null,
            country: null,
            workType: WorkType::Onsite,
        ));

        $this->assertFalse($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::Unknown, $result->category);
    }

    #[Test]
    public function classifies_remote_only_as_unknown(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: null,
            country: null,
            workType: WorkType::Remote,
            rawLocationStrings: ['Remote'],
        ));

        $this->assertFalse($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::Unknown, $result->category);
    }

    #[Test]
    public function classifies_hybrid_istanbul_as_turkey_relevant(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'Istanbul',
            country: 'Turkey',
            workType: WorkType::Hybrid,
            rawLocationStrings: ['Hybrid - Istanbul'],
        ));

        $this->assertTrue($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::Istanbul, $result->category);
    }

    #[Test]
    public function classifies_multi_location_with_istanbul_as_turkey_relevant(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'Istanbul',
            country: 'Turkey',
            workType: WorkType::Hybrid,
            rawLocationStrings: ['Istanbul / London'],
        ));

        $this->assertTrue($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::Istanbul, $result->category);
        $this->assertSame('Istanbul', $result->city);
    }

    #[Test]
    public function classifies_country_tr_as_turkey_relevant(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: null,
            country: 'TR',
            workType: WorkType::Remote,
            rawLocationStrings: ['TR'],
        ));

        $this->assertTrue($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::RemoteTurkey, $result->category);
    }

    #[Test]
    public function classifies_country_turkiye_only_as_turkey_relevant(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: null,
            country: 'Türkiye',
            workType: WorkType::Onsite,
            rawLocationStrings: ['Türkiye'],
        ));

        $this->assertTrue($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::OtherTurkey, $result->category);
        $this->assertSame('Türkiye', $result->country);
    }

    #[Test]
    public function classifies_ankara_without_country_as_turkey_relevant(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'Ankara',
            country: null,
            workType: WorkType::Onsite,
            rawLocationStrings: ['Ankara'],
        ));

        $this->assertTrue($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::OtherTurkey, $result->category);
        $this->assertSame('Ankara', $result->city);
    }

    #[Test]
    public function classifies_istanbul_without_country_as_turkey_relevant(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'İstanbul',
            country: null,
            workType: WorkType::Onsite,
            rawLocationStrings: ['İstanbul'],
        ));

        $this->assertTrue($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::Istanbul, $result->category);
    }

    #[Test]
    public function classifies_foreign_city_with_remote_flag_as_foreign(): void
    {
        $result = $this->classifier->classify(LocationInput::fromSignals(
            city: 'London',
            country: 'UK',
            workType: WorkType::Remote,
            rawLocationStrings: ['London, UK'],
        ));

        $this->assertFalse($result->isTurkeyRelevant);
        $this->assertSame(TurkeyLocationCategory::Foreign, $result->category);
    }
}
