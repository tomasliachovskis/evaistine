<?php

namespace Tests\Unit;

use App\Support\PharmacyName;
use Tests\TestCase;

class PharmacyNameTest extends TestCase
{
    /**
     * @dataProvider phraseProvider
     */
    public function test_phrase(string $name, string $case, string $expected): void
    {
        $this->assertSame($expected, PharmacyName::phrase($name, $case));
    }

    public static function phraseProvider(): array
    {
        return [
            'name without vaistinė gets the noun' => ['Camelia', 'locative', 'Camelia vaistinėje'],
            'Apotheka genitive plural' => ['Apotheka', 'genitive_plural', 'Apotheka vaistinių'],
            'Eurovaistinė inflects itself' => ['Eurovaistinė', 'locative', 'Eurovaistinėje'],
            'Eurovaistinė accusative' => ['Eurovaistinė', 'accusative', 'Eurovaistinę'],
            'Gintarinė adjective inflects too' => ['Gintarinė vaistinė', 'locative', 'Gintarinėje vaistinėje'],
            'Gintarinė genitive plural' => ['Gintarinė vaistinė', 'genitive_plural', 'Gintarinių vaistinių'],
            'Benu locative plural' => ['Benu vaistinė', 'locative_plural', 'Benu vaistinėse'],
            'N vaistinė genitive' => ['N vaistinė', 'genitive', 'N vaistinės'],
            'Ramunėlės nominative' => ['Ramunėlės vaistinė', 'nominative', 'Ramunėlės vaistinė'],
            'Mano vaistinė locative' => ['Mano vaistinė', 'locative', 'Mano vaistinėje'],
            'Piliulė inflects without the noun' => ['Piliulė', 'locative', 'Piliulėje'],
            '100 metų vaistinė genitive plural' => ['100 metų vaistinė', 'genitive_plural', '100 metų vaistinių'],
            'domain brand is never declined' => ['InternetineVaistine.lt', 'locative', 'InternetineVaistine.lt'],
            'unknown name with vaistinė is left alone' => ['Nauja vaistinė', 'locative', 'Nauja vaistinė'],
        ];
    }

    // Every configured pharmacy, in every case, without "vaistinė" twice.
    public function test_no_configured_pharmacy_doubles_the_noun(): void
    {
        foreach (array_column(config('stores.stores'), 'name') as $name) {
            foreach (['nominative', 'genitive', 'accusative', 'locative', 'plural', 'genitive_plural', 'locative_plural'] as $case) {
                $phrase = PharmacyName::phrase($name, $case);
                $this->assertLessThanOrEqual(1, preg_match_all('/vaistin/iu', $phrase), "{$name} ({$case}): {$phrase}");
            }
        }
    }

    public function test_unknown_case_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PharmacyName::phrase('Camelia', 'dative');
    }
}
