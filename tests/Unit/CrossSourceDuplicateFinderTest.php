<?php

namespace Tests\Unit;

use App\Services\CrossSourceDuplicateFinder;
use App\Services\ProductDuplicateMergeService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CrossSourceDuplicateFinderTest extends TestCase
{
    private function finder(): CrossSourceDuplicateFinder
    {
        return new CrossSourceDuplicateFinder(new ProductDuplicateMergeService());
    }

    public static function samePairs(): array
    {
        return [
            'abbreviated name, variant count in info' => ['Šaldytos bulvių lazdelės NATALI, 1 kg', '(2 rūšių)', 'Šald.bulvių lazdelės NATALI (2 rūš.), 1 kg', null],
            'info carried inline in web name' => ['Kiaulienos šonkauliai RIMI, 1 kg', 'atšaldyti', 'Kiaulienos šonkauliai RIMI atšaldyti, 1 kg', '3,49 €/kg'],
            'short-hand abbreviations' => ['Švieži viščiukų broilerių filė gabaliukai be odos RIMI, 400 g', 'A klasė', 'Višč. br. filė gabaliukai RIMI, A kl., 400 g', '6,72 €/kg'],
            'extra flyer word' => ['Skalbimo gelis PERSIL ACTIVE GEL, 3.96 l', '88 sk.', 'Skal. gelis PERSIL ACTIVE, 88 sk., 3.96 l', '8,08 €/l'],
        ];
    }

    #[DataProvider('samePairs')]
    public function test_matches_same_product(string $flyerName, ?string $flyerInfo, string $webName, ?string $webInfo): void
    {
        $this->assertNotNull($this->finder()->matchScore($flyerName, $flyerInfo, $webName, $webInfo));
    }

    public static function differentPairs(): array
    {
        return [
            'different brand' => ['Vingiuoto pjaustymo bulvių traškučiai ESTRELLA, 250 g', '(6 rūšys)', 'Jog. žolel. sk. bulvių traškučiai LAYS, 180 g', '19,94 €/kg'],
            'different product type' => ['Kūdikių servetėlės PAMPERS SENSITIVE, 5 x 52 vnt', null, 'Maud. sauskelnės PAMPERS, 14+ kg, 10 vnt', '0,84 €/vnt.'],
            'multi-variant flyer offer vs one variant' => ['Skystas skalbiklis WOOLITE, 4.5 l', '(3 rūšys)', 'Skystasis skalbiklis WOOLITE Color, 4.5 l', '4,44 €/l'],
            'web variant brand word missing on flyer' => ['Skystas skalbiklis WOOLITE, 4.5 l', null, 'Skystas skalbiklis WOOLITE DARK, 4.5 l', '4,44 €/l'],
            'pack size differs' => ['Šaldytos bulvių lazdelės NATALI, 1 kg', null, 'Šald. bulvių lazdelės NATALI, 750 g', null],
            'flavor differs' => ['Varškė GRAIKIŠKA AMFORA su mangais, 200 g', '0,8 % rieb.', 'Varškė GRAIKIŠKA AMFORA su avietėmis, 0,8 % rieb., 200 g', null],
        ];
    }

    #[DataProvider('differentPairs')]
    public function test_rejects_different_product(string $flyerName, ?string $flyerInfo, string $webName, ?string $webInfo): void
    {
        $this->assertNull($this->finder()->matchScore($flyerName, $flyerInfo, $webName, $webInfo));
    }
}
