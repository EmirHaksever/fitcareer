<?php

declare(strict_types=1);

namespace App\Services\Scraper;

use App\Enums\JobOrigin;
use App\Enums\TurkeyLocationCategory;
use App\Enums\WorkType;
use App\Services\Scraper\DTO\LocationClassificationResult;
use App\Services\Scraper\DTO\LocationInput;
use Illuminate\Database\Eloquent\Builder;

class LocationClassificationService
{
    /** @var list<string> */
    private const TURKEY_COUNTRY_TOKENS = [
        'turkey',
        'turkiye',
        'türkiye',
        'tr',
    ];

    /** @var array<string, list<string>> normalized key => display aliases */
    private const TURKEY_CITIES = [
        'istanbul' => ['istanbul', 'i̇stanbul', 'İstanbul'],
        'ankara' => ['ankara'],
        'izmir' => ['izmir', 'i̇zmir', 'İzmir'],
        'bursa' => ['bursa'],
        'antalya' => ['antalya'],
        'adana' => ['adana'],
        'konya' => ['konya'],
        'kocaeli' => ['kocaeli'],
        'mersin' => ['mersin'],
        'kayseri' => ['kayseri'],
        'gaziantep' => ['gaziantep'],
        'diyarbakir' => ['diyarbakir', 'diyarbakır'],
        'samsun' => ['samsun'],
        'trabzon' => ['trabzon'],
        'eskisehir' => ['eskisehir', 'eskişehir'],
        'erzurum' => ['erzurum'],
        'sakarya' => ['sakarya'],
        'denizli' => ['denizli'],
        'tekirdag' => ['tekirdag', 'tekirdağ'],
        'balikesir' => ['balikesir', 'balıkesir'],
        'manisa' => ['manisa'],
        'mugla' => ['mugla', 'muğla'],
        'hatay' => ['hatay'],
        'malatya' => ['malatya'],
        'van' => ['van'],
        'kahramanmaras' => ['kahramanmaras', 'kahramanmaraş'],
        'sanliurfa' => ['sanliurfa', 'şanlıurfa', 'şanliurfa'],
        'elazig' => ['elazig', 'elazığ'],
        'canakkale' => ['canakkale', 'çanakkale'],
        'aydin' => ['aydin', 'aydın'],
        'ordu' => ['ordu'],
        'rize' => ['rize'],
        'bolu' => ['bolu'],
        'corum' => ['corum', 'çorum'],
        'kutahya' => ['kutahya', 'kütahya'],
        'afyonkarahisar' => ['afyonkarahisar'],
        'isparta' => ['isparta'],
        'burdur' => ['burdur'],
        'yalova' => ['yalova'],
        'duzce' => ['duzce', 'düzce'],
        'nevsehir' => ['nevsehir', 'nevşehir'],
        'nigde' => ['nigde', 'niğde'],
        'kirklareli' => ['kirklareli', 'kırklareli'],
        'edirne' => ['edirne'],
        'osmaniye' => ['osmaniye'],
        'karabuk' => ['karabuk', 'karabük'],
        'zonguldak' => ['zonguldak'],
        'bartin' => ['bartin', 'bartın'],
        'kastamonu' => ['kastamonu'],
        'sinop' => ['sinop'],
        'amasya' => ['amasya'],
        'tokat' => ['tokat'],
        'sivas' => ['sivas'],
        'yozgat' => ['yozgat'],
        'kars' => ['kars'],
        'agri' => ['agri', 'ağrı'],
        'igdir' => ['igdir', 'iğdır'],
        'mus' => ['mus', 'muş'],
        'bitlis' => ['bitlis'],
        'siirt' => ['siirt'],
        'sirnak' => ['sirnak', 'şırnak'],
        'mardin' => ['mardin'],
        'batman' => ['batman'],
        'bingol' => ['bingol', 'bingöl'],
        'tunceli' => ['tunceli'],
        'ardahan' => ['ardahan'],
        'artvin' => ['artvin'],
        'giresun' => ['giresun'],
        'kilis' => ['kilis'],
        'bilecik' => ['bilecik'],
        'usak' => ['usak', 'uşak'],
    ];

    /** @var list<string> normalized foreign country/region tokens */
    private const FOREIGN_COUNTRY_TOKENS = [
        'united states',
        'usa',
        'u.s.a',
        'u.s.',
        'united kingdom',
        'uk',
        'u.k.',
        'great britain',
        'england',
        'scotland',
        'wales',
        'germany',
        'deutschland',
        'france',
        'australia',
        'singapore',
        'canada',
        'netherlands',
        'holland',
        'belgium',
        'spain',
        'italy',
        'portugal',
        'sweden',
        'norway',
        'denmark',
        'finland',
        'switzerland',
        'austria',
        'poland',
        'czech republic',
        'czechia',
        'hungary',
        'romania',
        'bulgaria',
        'greece',
        'ireland',
        'new zealand',
        'japan',
        'china',
        'india',
        'brazil',
        'mexico',
        'south africa',
        'israel',
        'uae',
        'united arab emirates',
        'saudi arabia',
        'qatar',
        'south korea',
        'korea',
        'hong kong',
        'taiwan',
        'philippines',
        'indonesia',
        'malaysia',
        'thailand',
        'vietnam',
        'pakistan',
        'egypt',
        'nigeria',
        'kenya',
        'argentina',
        'chile',
        'colombia',
        'russia',
        'ukraine',
    ];

    /** @var list<string> normalized global region tokens */
    private const GLOBAL_REGION_TOKENS = [
        'europe',
        'eu',
        'emea',
        'apac',
        'asia pacific',
        'worldwide',
        'global',
        'anywhere',
        'international',
        'americas',
        'north america',
        'south america',
        'latin america',
        'latam',
        'middle east',
        'mena',
        'africa',
        'oceania',
    ];

    /** @var list<string> normalized foreign city tokens */
    private const FOREIGN_CITY_TOKENS = [
        'london',
        'berlin',
        'paris',
        'sydney',
        'melbourne',
        'singapore',
        'amsterdam',
        'munich',
        'frankfurt',
        'dublin',
        'zurich',
        'geneva',
        'barcelona',
        'madrid',
        'lisbon',
        'stockholm',
        'copenhagen',
        'oslo',
        'helsinki',
        'warsaw',
        'prague',
        'vienna',
        'brussels',
        'new york',
        'san francisco',
        'los angeles',
        'chicago',
        'boston',
        'seattle',
        'austin',
        'toronto',
        'vancouver',
        'montreal',
        'dubai',
        'tokyo',
        'hong kong',
        'mumbai',
        'bangalore',
        'delhi',
    ];

    public function classify(LocationInput $input): LocationClassificationResult
    {
        $rawStrings = $this->collectRawStrings($input);
        $searchBlob = $this->buildSearchBlob($input, $rawStrings);

        if ($this->hasExplicitTurkeyRemote($searchBlob)) {
            return $this->buildResult(
                category: TurkeyLocationCategory::RemoteTurkey,
                isTurkeyRelevant: true,
                city: $this->resolvePrimaryCity($input, $rawStrings),
                country: $this->preserveCountryOrDefault($input, $input->country, 'Turkey'),
            );
        }

        if ($this->hasGlobalRemoteOnly($searchBlob, $input->workType)) {
            return $this->buildResult(
                category: TurkeyLocationCategory::Foreign,
                isTurkeyRelevant: false,
                city: $input->city,
                country: $input->country,
            );
        }

        $turkeyCountry = $this->isTurkeyCountryToken($input->country)
            || $this->containsTurkeyCountryToken($searchBlob);
        $turkeyCityKey = $this->detectTurkeyCityKey($input->city, $searchBlob);

        if ($this->isForeignCountryToken($input->country) && ! $turkeyCountry && $turkeyCityKey === null) {
            return $this->buildResult(
                category: TurkeyLocationCategory::Foreign,
                isTurkeyRelevant: false,
                city: $input->city,
                country: $input->country,
            );
        }

        if ($this->containsForeignRegionToken($searchBlob) && ! $turkeyCountry && $turkeyCityKey === null) {
            return $this->buildResult(
                category: TurkeyLocationCategory::Foreign,
                isTurkeyRelevant: false,
                city: $input->city,
                country: $input->country,
            );
        }

        if ($this->containsForeignCityToken($searchBlob) && ! $turkeyCountry && $turkeyCityKey === null) {
            return $this->buildResult(
                category: TurkeyLocationCategory::Foreign,
                isTurkeyRelevant: false,
                city: $input->city,
                country: $input->country,
            );
        }

        if ($turkeyCountry || $turkeyCityKey !== null) {
            $resolvedCity = $this->resolvePrimaryCity($input, $rawStrings, $turkeyCityKey);
            $resolvedCountry = $this->resolveCountry($input, $turkeyCountry, $turkeyCityKey);

            if ($input->workType === WorkType::Remote && ! $this->hasOnsiteHybridSignal($searchBlob)) {
                return $this->buildResult(
                    category: TurkeyLocationCategory::RemoteTurkey,
                    isTurkeyRelevant: true,
                    city: $resolvedCity,
                    country: $resolvedCountry,
                );
            }

            $category = $turkeyCityKey === 'istanbul'
                ? TurkeyLocationCategory::Istanbul
                : TurkeyLocationCategory::OtherTurkey;

            return $this->buildResult(
                category: $category,
                isTurkeyRelevant: true,
                city: $resolvedCity,
                country: $resolvedCountry,
            );
        }

        if ($this->isRemoteOnlySignal($searchBlob, $input)) {
            return $this->buildResult(
                category: TurkeyLocationCategory::Unknown,
                isTurkeyRelevant: false,
                city: $input->city,
                country: $input->country,
            );
        }

        if ($input->city === null && $input->country === null && ($input->workType === null || $input->workType === WorkType::Remote)) {
            return $this->buildResult(
                category: TurkeyLocationCategory::Unknown,
                isTurkeyRelevant: false,
                city: null,
                country: null,
            );
        }

        if ($input->city === null && $input->country === null) {
            return $this->buildResult(
                category: TurkeyLocationCategory::Unknown,
                isTurkeyRelevant: false,
                city: null,
                country: null,
            );
        }

        return $this->buildResult(
            category: TurkeyLocationCategory::Foreign,
            isTurkeyRelevant: false,
            city: $input->city,
            country: $input->country,
        );
    }

    public function isTurkeyRelevant(?string $city, ?string $country, ?WorkType $workType, array $rawLocationStrings = []): bool
    {
        return $this->classify(LocationInput::fromSignals($city, $country, $workType, $rawLocationStrings))
            ->isTurkeyRelevant;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     */
    public function applyTurkeyRelevantScope(Builder $builder, bool $includeGlobal = false): void
    {
        if ($includeGlobal) {
            return;
        }

        $builder->where(function (Builder $query): void {
            $query->where('jobs.source', JobOrigin::Internal->value)
                ->orWhere(function (Builder $scraped): void {
                    $this->applyScrapedTurkeyRelevantConditions($scraped);
                });
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     */
    private function applyScrapedTurkeyRelevantConditions(Builder $builder): void
    {
        $builder->where(function (Builder $query): void {
            $query->where(function (Builder $countryQuery): void {
                foreach (self::TURKEY_COUNTRY_TOKENS as $token) {
                    $countryQuery->orWhereRaw('LOWER(jobs.country) = ?', [$token]);
                }
            })
                ->orWhere(function (Builder $cityQuery): void {
                    $this->applyTurkeyCityColumnConditions($cityQuery, 'jobs.city');
                    $this->applyTurkeyCityColumnConditions($cityQuery, 'jobs.country');
                })
                ->orWhere(function (Builder $remoteQuery): void {
                    $remoteQuery->where('jobs.work_type', WorkType::Remote->value)
                        ->where(function (Builder $remoteTurkeyQuery): void {
                            $remoteTurkeyQuery->where(function (Builder $countryQuery): void {
                                foreach (self::TURKEY_COUNTRY_TOKENS as $token) {
                                    $countryQuery->orWhereRaw('LOWER(jobs.country) = ?', [$token]);
                                }
                            })->orWhere(function (Builder $cityQuery): void {
                                $this->applyTurkeyCityColumnConditions($cityQuery, 'jobs.city');
                                $this->applyTurkeyCityColumnConditions($cityQuery, 'jobs.country');
                            });
                        });
                });
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     */
    private function applyTurkeyCityColumnConditions(Builder $builder, string $column): void
    {
        foreach (self::TURKEY_CITIES as $cityKey => $aliases) {
            foreach ($aliases as $alias) {
                $builder->orWhereRaw("LOWER({$column}) = ?", [mb_strtolower($alias)]);
            }

            if (mb_strlen($cityKey) > 3) {
                $builder->orWhereRaw("LOWER({$column}) = ?", [$cityKey]);
            }
        }
    }

    /**
     * @param  list<string>  $rawStrings
     */
    private function collectRawStrings(LocationInput $input): array
    {
        $strings = $input->rawLocationStrings;

        if ($input->city !== null) {
            $strings[] = $input->city;
        }

        if ($input->country !== null) {
            $strings[] = $input->country;
        }

        return array_values(array_unique(array_filter($strings)));
    }

    /**
     * @param  list<string>  $rawStrings
     */
    private function buildSearchBlob(LocationInput $input, array $rawStrings): string
    {
        return $this->normalizeText(implode(' | ', $rawStrings));
    }

    private function hasExplicitTurkeyRemote(string $searchBlob): bool
    {
        $patterns = [
            '/\bremote\b[\s,\-\/]*\bturkey\b/u',
            '/\bremote\b[\s,\-\/]*\bturkiye\b/u',
            '/\bremote\b[\s,\-\/]*\btürkiye\b/u',
            '/\bturkey\b[\s,\-\/]*\(\s*remote\s*\)/u',
            '/\bturkiye\b[\s,\-\/]*\(\s*remote\s*\)/u',
            '/\btürkiye\b[\s,\-\/]*\(\s*remote\s*\)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $searchBlob) === 1) {
                return true;
            }
        }

        return false;
    }

    private function hasGlobalRemoteOnly(string $searchBlob, ?WorkType $workType): bool
    {
        if (! str_contains($searchBlob, 'remote')) {
            return false;
        }

        foreach (self::GLOBAL_REGION_TOKENS as $token) {
            if (str_contains($searchBlob, $token)) {
                return true;
            }
        }

        if ($this->containsForeignCityToken($searchBlob) || $this->containsForeignCountryToken($searchBlob)) {
            return true;
        }

        return false;
    }

    private function isRemoteOnlySignal(string $searchBlob, LocationInput $input): bool
    {
        if ($input->workType !== WorkType::Remote) {
            return false;
        }

        if ($input->city !== null || $input->country !== null) {
            $normalizedCountry = $this->normalizeText($input->country);
            $normalizedCity = $this->normalizeText($input->city);

            if ($normalizedCountry !== '' && $normalizedCountry !== 'remote') {
                return false;
            }

            if ($normalizedCity !== '' && $normalizedCity !== 'remote') {
                return false;
            }
        }

        return $searchBlob === 'remote'
            || preg_match('/^\s*remote\s*$/u', $searchBlob) === 1
            || ($input->city === null && $input->country === null);
    }

    private function hasOnsiteHybridSignal(string $searchBlob): bool
    {
        return str_contains($searchBlob, 'hybrid') || str_contains($searchBlob, 'onsite') || str_contains($searchBlob, 'on-site');
    }

    /**
     * @param  list<string>  $rawStrings
     */
    private function resolvePrimaryCity(LocationInput $input, array $rawStrings, ?string $preferredCityKey = null): ?string
    {
        if ($input->city !== null && $this->detectTurkeyCityKey($input->city, '') !== null) {
            return $input->city;
        }

        if ($preferredCityKey !== null) {
            if ($input->city === null
                && $input->country !== null
                && $this->detectTurkeyCityKey($input->country, '') === $preferredCityKey) {
                return $input->country;
            }

            return $this->displayNameForCityKey($preferredCityKey);
        }

        $detectedKey = $this->detectTurkeyCityKey($input->city, $this->buildSearchBlob($input, $rawStrings));

        if ($detectedKey !== null) {
            return $this->displayNameForCityKey($detectedKey);
        }

        if ($input->city !== null && ! $this->isTurkeyCountryToken($input->city)) {
            return $input->city;
        }

        $swappedCity = $this->detectTurkeyCityKey($input->country, '');

        if ($swappedCity !== null && $input->city === null && $input->country !== null) {
            return $input->country;
        }

        return $swappedCity !== null ? $this->displayNameForCityKey($swappedCity) : $input->city;
    }

    private function resolveCountry(LocationInput $input, bool $turkeyCountry, ?string $turkeyCityKey = null): ?string
    {
        if ($this->hasConflictingTurkeyCityFields($input->city, $input->country)) {
            return $input->country;
        }

        if ($input->country !== null && $this->isTurkeyCountryToken($input->country)) {
            return $input->country;
        }

        $countryAsCityKey = $this->detectTurkeyCityKey($input->country, '');

        if ($countryAsCityKey !== null && ! $this->isTurkeyCountryToken($input->country)) {
            $cityMatchesCountryField = $input->city === null
                || $this->normalizeText((string) $input->city) === $this->normalizeText((string) $input->country);

            if ($cityMatchesCountryField) {
                return $this->canonicalTurkeyCountryLabel($input);
            }
        }

        if ($input->country !== null && $countryAsCityKey === null && ! $this->isForeignCountryToken($input->country)) {
            return $input->country;
        }

        if ($turkeyCountry || $turkeyCityKey !== null) {
            return $this->canonicalTurkeyCountryLabel($input);
        }

        return $input->country;
    }

    private function preserveCountryOrDefault(LocationInput $input, ?string $country, string $default): ?string
    {
        if ($country !== null && $this->isTurkeyCountryToken($country)) {
            return $country;
        }

        if ($country !== null && $this->detectTurkeyCityKey($country, '') !== null) {
            return $this->canonicalTurkeyCountryLabel($input);
        }

        if ($country !== null && ! $this->isForeignCountryToken($country)) {
            return $country;
        }

        return $default;
    }

    private function hasConflictingTurkeyCityFields(?string $city, ?string $country): bool
    {
        $cityKey = $this->detectTurkeyCityKey($city, '');
        $countryKey = $this->detectTurkeyCityKey($country, '');

        return $cityKey !== null
            && $countryKey !== null
            && $cityKey !== $countryKey;
    }

    private function canonicalTurkeyCountryLabel(LocationInput $input): string
    {
        foreach ($input->rawLocationStrings as $string) {
            $normalized = mb_strtolower($this->normalizeText($string));

            if (str_contains($normalized, 'türkiye') || str_contains($normalized, 'turkiye')) {
                return 'Türkiye';
            }
        }

        if ($input->country !== null && $this->isTurkeyCountryToken($input->country)) {
            return $input->country;
        }

        return 'Turkey';
    }

    private function buildResult(
        TurkeyLocationCategory $category,
        bool $isTurkeyRelevant,
        ?string $city,
        ?string $country,
    ): LocationClassificationResult {
        return new LocationClassificationResult(
            category: $category,
            isTurkeyRelevant: $isTurkeyRelevant,
            city: $city,
            country: $country,
        );
    }

    private function detectTurkeyCityKey(?string $city, string $searchBlob): ?string
    {
        $segments = $this->extractSegments($city, $searchBlob);

        foreach ($segments as $segment) {
            foreach (self::TURKEY_CITIES as $key => $aliases) {
                foreach ($aliases as $alias) {
                    if ($this->segmentMatchesCity($segment, $this->normalizeText($alias))) {
                        return $key;
                    }
                }

                if ($this->segmentMatchesCity($segment, $key)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractSegments(?string $city, string $searchBlob): array
    {
        $parts = [];

        if ($city !== null && trim($city) !== '') {
            $parts[] = $this->normalizeText($city);
        }

        if ($searchBlob !== '') {
            foreach (preg_split('/[\|\/,]+/u', $searchBlob) ?: [] as $segment) {
                $normalized = $this->normalizeText($segment);

                if ($normalized !== '') {
                    $parts[] = $normalized;
                }
            }
        }

        return array_values(array_unique($parts));
    }

    private function segmentMatchesCity(string $segment, string $cityToken): bool
    {
        if ($segment === $cityToken) {
            return true;
        }

        if (mb_strlen($cityToken) <= 3) {
            return preg_match('/\b'.preg_quote($cityToken, '/').'\b/u', $segment) === 1;
        }

        return str_contains($segment, $cityToken);
    }

    private function isTurkeyCountryToken(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array($this->normalizeText($value), self::TURKEY_COUNTRY_TOKENS, true);
    }

    private function containsTurkeyCountryToken(string $searchBlob): bool
    {
        foreach (self::TURKEY_COUNTRY_TOKENS as $token) {
            if (preg_match('/\b'.preg_quote($token, '/').'\b/u', $searchBlob) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isForeignCountryToken(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = $this->normalizeText($value);

        if ($normalized === '' || $this->isTurkeyCountryToken($value)) {
            return false;
        }

        return $this->tokenInList($normalized, self::FOREIGN_COUNTRY_TOKENS)
            || $this->tokenInList($normalized, self::GLOBAL_REGION_TOKENS);
    }

    private function containsForeignCountryToken(string $searchBlob): bool
    {
        foreach ([...self::FOREIGN_COUNTRY_TOKENS, ...self::GLOBAL_REGION_TOKENS] as $token) {
            if (preg_match('/\b'.preg_quote($token, '/').'\b/u', $searchBlob) === 1) {
                return true;
            }
        }

        return false;
    }

    private function containsForeignRegionToken(string $searchBlob): bool
    {
        return $this->containsForeignCountryToken($searchBlob);
    }

    private function containsForeignCityToken(string $searchBlob): bool
    {
        foreach (self::FOREIGN_CITY_TOKENS as $token) {
            if (preg_match('/\b'.preg_quote($token, '/').'\b/u', $searchBlob) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $tokens
     */
    private function tokenInList(string $value, array $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($value === $token || str_contains($value, $token)) {
                return true;
            }
        }

        return false;
    }

    private function displayNameForCityKey(string $cityKey): string
    {
        $aliases = self::TURKEY_CITIES[$cityKey] ?? [$cityKey];

        foreach ($aliases as $alias) {
            if (str_contains($alias, 'İ') || str_contains($alias, 'ı') || str_contains($alias, 'ş') || str_contains($alias, 'ğ') || str_contains($alias, 'ü') || str_contains($alias, 'ö') || str_contains($alias, 'ç')) {
                return $alias;
            }
        }

        return ucfirst($cityKey);
    }

    private function normalizeText(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $normalized = mb_strtolower(trim($value));

        return strtr($normalized, [
            'ı' => 'i',
            'İ' => 'i',
            'ş' => 's',
            'ğ' => 'g',
            'ü' => 'u',
            'ö' => 'o',
            'ç' => 'c',
        ]);
    }
}
