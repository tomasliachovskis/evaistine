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
            'unknown name with vaistinė is left alone' => ['Nauja vaistinė', 'locative', 'Nauja vaistinė'],
        ];
    }

    public function test_unknown_case_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PharmacyName::phrase('Camelia', 'dative');
    }
}
