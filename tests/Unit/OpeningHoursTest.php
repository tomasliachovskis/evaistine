<?php

namespace Tests\Unit;

use App\Support\OpeningHours;
use PHPUnit\Framework\TestCase;

class OpeningHoursTest extends TestCase
{
    private const WEEK = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    /**
     * @dataProvider dayProvider
     */
    public function test_parses_scraped_day_spellings(string $raw, array|string|null $expected): void
    {
        $parsed = OpeningHours::parseDay($raw);

        $this->assertSame($expected, is_array($parsed) ? $parsed['label'] : $parsed);
    }

    public static function dayProvider(): array
    {
        return [
            'plain' => ['08:00-22:00', '08:00–22:00'],
            'en dash' => ['08:00–22:00', '08:00–22:00'],
            'spaced' => ['08:00 - 22:00', '08:00–22:00'],
            'hours only' => ['8-22', '08:00–22:00'],
            'mixed' => ['8:30-21', '08:30–21:00'],
            'midnight as 24:00' => ['08:00-24:00', '08:00–24:00'],
            'midnight as 00:00' => ['08:00-00:00', '08:00–24:00'],
            'closed' => ['Nedirba', 'closed'],
            'closed lowercase' => ['nedirbame', 'closed'],
            'empty' => ['', null],
            'dash only' => ['-', null],
            'free text' => ['08:00-22:00 (vasarą)', null],
        ];
    }

    public function test_summary_collapses_an_identical_week(): void
    {
        $this->assertSame('Kasdien 08:00–22:00', OpeningHours::summary(array_fill_keys(self::WEEK, '8-22')));
    }

    public function test_summary_groups_consecutive_days(): void
    {
        $hours = array_fill_keys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], '08:00-22:00')
            + ['saturday' => '09:00-21:00', 'sunday' => 'Nedirba'];

        $this->assertSame('Pr–Pn 08:00–22:00, Št 09:00–21:00, Sk nedirba', OpeningHours::summary($hours));
    }

    public function test_summary_skips_unknown_days_and_is_null_without_data(): void
    {
        $this->assertSame('Pr 08:00–20:00', OpeningHours::summary(['monday' => '08:00-20:00', 'tuesday' => '']));
        $this->assertNull(OpeningHours::summary([]));
    }

    public function test_city_facts_name_the_exceptions(): void
    {
        $locations = collect([
            $this->location('A g. 1', '07:00-22:00', '09:00-20:00'),
            $this->location('B g. 2', '08:00-24:00', '09:00-20:00'),
            $this->location('C g. 3', '08:00-22:00', 'Nedirba'),
            $this->location('D g. 4', '00:00-24:00', '00:00-24:00'),
            $this->location('E g. 5', '08:00-21:00', '09:00-18:00'),
        ]);

        $this->assertSame([
            'Visą parą dirba: D g. 4.',
            'Anksčiausiai darbo dienomis atsidaro – nuo 07:00: A g. 1.',
            'Ilgiausiai darbo dienomis dirba – iki 24:00: B g. 2.',
            'Anksčiausiai darbo dienomis uždaro – 21:00: E g. 5.',
            'Sekmadienį nedirba: C g. 3.',
            'Sekmadienį ilgiausiai dirba – iki 24:00: D g. 4.',
            'Sekmadienį anksčiausiai uždaro – 18:00: E g. 5.',
        ], OpeningHours::cityFacts($locations));
    }

    public function test_city_facts_skip_what_every_location_shares(): void
    {
        // Alytus-style: everyone opens at 08:00 and works Sunday — the only
        // real information is which store closes earlier than the rest.
        $locations = collect([
            $this->location('A g. 1', '08:00-22:00', '08:00-22:00'),
            $this->location('B g. 2', '08:00-22:00', '08:00-22:00'),
            $this->location('C g. 3', '08:00-22:00', '08:00-22:00'),
            $this->location('D g. 4', '08:00-21:00', '08:00-21:00'),
        ]);

        $this->assertSame([
            'Anksčiausiai darbo dienomis uždaro – 21:00: D g. 4.',
            'Sekmadienį anksčiausiai uždaro – 21:00: D g. 4.',
        ], OpeningHours::cityFacts($locations));
    }

    public function test_city_facts_list_the_few_sunday_openers_with_hours(): void
    {
        $locations = collect([
            $this->location('A g. 1', '08:00-22:00', 'Nedirba'),
            $this->location('B g. 2', '08:00-22:00', 'Nedirba'),
            $this->location('C g. 3', '08:00-22:00', '10:00-16:00'),
        ]);

        $this->assertSame(['Sekmadienį dirba tik: C g. 3 (10:00–16:00).'], OpeningHours::cityFacts($locations));
    }

    public function test_long_address_lists_are_capped(): void
    {
        $locations = collect(range(1, 10))->map(fn ($i) => $this->location("G{$i} g.", $i <= 4 ? '07:00-22:00' : '08:00-22:00', '08:00-22:00'));

        $this->assertSame(
            ['Anksčiausiai darbo dienomis atsidaro – nuo 07:00: G1 g., G2 g., G3 g. ir dar 1.'],
            OpeningHours::cityFacts($locations)
        );
    }

    public function test_no_city_facts_when_identical_or_unparseable(): void
    {
        $this->assertSame([], OpeningHours::cityFacts(collect([
            $this->location('A g. 1', '08:00-22:00', '09:00-20:00'),
            $this->location('B g. 2', '08:00-22:00', '09:00-20:00'),
        ])));

        $this->assertSame([], OpeningHours::cityFacts(collect([
            $this->location('A g. 1', '07:00-22:00', ''),
        ])));

        $this->assertSame([], OpeningHours::cityFacts(collect([
            $this->location('A g. 1', '', ''),
            $this->location('B g. 2', '', ''),
            $this->location('C g. 3', '08:00-22:00', ''),
        ])));
    }

    private function location(string $address, string $weekday, string $sunday): array
    {
        return [
            'address' => $address,
            'hours' => array_fill_keys(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'], $weekday) + ['sunday' => $sunday],
        ];
    }
}
