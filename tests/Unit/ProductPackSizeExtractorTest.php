<?php

namespace Tests\Unit;

use App\Support\ProductPackSizeExtractor;
use PHPUnit\Framework\TestCase;

class ProductPackSizeExtractorTest extends TestCase
{
    /**
     * @dataProvider extractProvider
     */
    public function test_extract_and_strip(?string $input, ?string $expectedSize, ?string $expectedInfo): void
    {
        $result = ProductPackSizeExtractor::extractAndStrip($input);

        $this->assertSame($expectedSize, $result['size'], 'Size mismatch for input: ' . var_export($input, true));
        $this->assertSame($expectedInfo, $result['info'], 'Info mismatch for input: ' . var_export($input, true));
    }

    public function test_extract_returns_size_only(): void
    {
        $this->assertSame('145 g', ProductPackSizeExtractor::extract('145 g, 1 kg – 13,72 €'));
        $this->assertNull(ProductPackSizeExtractor::extract('Įvairių rūšių'));
        $this->assertNull(ProductPackSizeExtractor::extract(null));
    }

    public static function extractProvider(): array
    {
        return [
            '1a absolute + comparative 1 kg (dash)' => ['145 g, 1 kg – 13,72 €', '145 g', '1 kg – 13,72 €'],
            '1b absolute + comparative 1 kg (hyphen)' => ['800 g, 1 kg - 6,86 €', '800 g', '1 kg - 6,86 €'],
            '1c prefix + absolute' => ['vytinta, 80 g, 1 kg - 18,63 €', '80 g', 'vytinta, 1 kg - 18,63 €'],
            '1d slash separator' => ['500 g / 1 kg = 12,98 €', '500 g', '1 kg = 12,98 €'],
            '1e ml + grynosios masės kaina' => ['690 ml; 1 kg grynosios masės kaina - 3,09 €', '690 ml', '1 kg grynosios masės kaina - 3,09 €'],
            '1f l + country' => ['2 rūšių, 0,75 l, 1 l – 6,39 €, Lietuva', '0.75 l', '2 rūšių, 1 l – 6,39 €, Lietuva'],

            '2a skard 160g' => ['1 skard. (160 g); 1 kg grynosios masės kaina - 11,19 €', '160 g', '1 skard.; 1 kg grynosios masės kaina - 11,19 €'],
            '2b dėž 125g space' => ['1 dėž. (125 g) 1 kg - 13,52 €', '125 g', '1 dėž. 1 kg - 13,52 €'],
            '2c pak 200g' => ['1 pak. (200 g), 1 kg - 21,95 €', '200 g', '1 pak., 1 kg - 21,95 €'],
            '2d vnt 45g' => ['1 vnt. (45 g), 1 kg - 42,00 €', '45 g', '1 vnt., 1 kg - 42,00 €'],
            '2e dėž 95g semicolon' => ['1 dėž. (95 g); 1 kg - 62,00 €', '95 g', '1 dėž.; 1 kg - 62,00 €'],
            '2f rink 5x18g' => ['1 rink. (5x18 g), 1 kg - 35,44 €', '5 x 18 g', '1 rink., 1 kg - 35,44 €'],
            '2g dėž 3x20g' => ['1 dėž. (3 x 20 g), 1 rink.', '3 x 20 g', '1 dėž., 1 rink.'],

            '3a just 1 kg' => ['1 kg', '1 kg', null],
            '3b just 200 g' => ['200 g', '200 g', null],
            '3c just 1 vnt.' => ['1 vnt.', '1 vnt', null],
            '3d just 1 l' => ['1 l', '1 l', null],
            '3e just 1,5 l' => ['1,5 l', '1.5 l', null],
            '3f just 1 rit.' => ['1 rit.', '1 rit.', null],
            '3g just 1 kompl.' => ['1 kompl.', '1 kompl.', null],
            '3h just 1 pora' => ['1 pora', '1 pora', null],
            '3i just 1 rink.' => ['1 rink.', '1 rink.', null],

            '4a virtas 1 kg' => ['virtas; a. r., 1 kg', '1 kg', 'virtas; a. r.'],
            '4b keptas 400g + 1kg' => ['keptas, a. r., 400 g, 1 kg - 7,48 €', '400 g', 'keptas, a. r., 1 kg - 7,48 €'],
            '4c žirneliai 690g' => ['Žalieji žirneliai, 690 g, 1 kg – 1,96 €', '690 g', 'Žalieji žirneliai, 1 kg – 1,96 €'],
            '4d rieb 855ml + 1l' => ['76 % rieb., 855 ml, 1 l – 4,67 €', '855 ml', '76 % rieb., 1 l – 4,67 €'],
            '4e UAT 1 l' => ['3,2 % rieb., UAT, 1 l', '1 l', '3,2 % rieb., UAT'],
            '4f 40% rieb. + 140 g' => ['40% rieb. s. m., 140 g', '140 g', '40% rieb. s. m.'],
            '4g I r. 400 g' => ['I r., 400 g', '400 g', 'I r.'],

            '5a 4x100g' => ['4x100 g, 1 kg – 2,38 €', '4 x 100 g', '1 kg – 2,38 €'],
            '5b 3 x 50g' => ['3 x 50 g, 2 rūšių, 29,93 Eur/kg', '3 x 50 g', '2 rūšių, 29,93 Eur/kg'],
            '5c 2 x 90g' => ['2 x 90 g, 16,61 Eur/kg. Pasiūlymas galioja 2026 04 06 - 05 03', '2 x 90 g', '16,61 Eur/kg. Pasiūlymas galioja 2026 04 06 - 05 03'],

            '6a first of list' => ['90 g, 120 g, 2 rūšių', '90 g', '120 g, 2 rūšių'],
            '6b range tight' => ['125 g-200 g, 4 rūšių', '125 g-200 g', '4 rūšių'],
            '6c range spaced' => ['40 g - 45 g, Įv. rūšių, ≥11,56 €/kg', '40 g - 45 g', 'Įv. rūšių, ≥11,56 €/kg'],

            '7a approx 790g' => ['~790 g', '~790 g', null],
            '7b approx 1kg' => ['~1 kg', '~1 kg', null],

            '8a rit first' => ['8 rit., 150 lap., 3 sl.', '8 rit.', '150 lap., 3 sl.'],
            '8b vnt + cm' => ['15 vnt., 40 cm', '15 vnt', '40 cm'],
            '8c skalb.' => ['20 skalb., 2 rūšių', '20 skalb.', '2 rūšių'],
            '8d vazono skersmuo' => ['1 vnt., vazono skersmuo 12 cm', '1 vnt', 'vazono skersmuo 12 cm'],
            '8e vnt + €/vnt' => ['10 vnt., 0,24 Eur/vnt.', '10 vnt', '0,24 Eur/vnt.'],

            '9a €/Pora' => ['2,66 €/Pora', null, '2,66 €/Pora'],
            '9b €/vnt.' => ['9,99 €/vnt.', null, '9,99 €/vnt.'],
            '9c €/kg' => ['7,19 €/kg', null, '7,19 €/kg'],
            '9d Eur/kg + IKI' => ['5,16 Eur/kg. Ir IKI EXPRESS', null, '5,16 Eur/kg. Ir IKI EXPRESS'],

            '10a Įvairių rūšių' => ['Įvairių rūšių', null, 'Įvairių rūšių'],
            '10b įv. rūšių' => ['įv. rūšių', null, 'įv. rūšių'],
            '10c 3 rūšių' => ['3 rūšių', null, '3 rūšių'],
            '10d 8 rūšių' => ['8 rūšių', null, '8 rūšių'],
            '10e B lygis' => ['B lygis', null, 'B lygis'],
            '10f Įvairių + IKI' => ['Įvairių rūšių. Ir IKI EXPRESS', null, 'Įvairių rūšių. Ir IKI EXPRESS'],

            '11a A klasė' => ['A klasė, 1 kg', '1 kg', 'A klasė'],
            '11b II kl.' => ['II kl., 1 kg', '1 kg', 'II kl.'],
            '11c be poliežuvinės mėsos' => ['be poliežuvinės mėsos, 1 kg', '1 kg', 'be poliežuvinės mėsos'],

            'null' => [null, null, null],
            'empty string' => ['', null, null],
            'whitespace' => ['   ', null, null],

            'float comma with space' => ['1,5 l', '1.5 l', null],
            'float comma no space' => ['1,5l', '1.5 l', null],
            'float dot with space' => ['1.5 l', '1.5 l', null],
            'float dot no space' => ['1.5l', '1.5 l', null],
            'float comma 0,75 l' => ['0,75 l', '0.75 l', null],
            'float comma 2,5 kg' => ['2,5 kg', '2.5 kg', null],

            'name with size suffix comma no space' => ['Alus Švyturys 0,5l', '0.5 l', 'Alus Švyturys'],
            'name with size suffix dot cap L' => ['Alus Švyturys 0.5 L', '0.5 l', 'Alus Švyturys'],
            'name with size prefix' => ['500 g Jautiena', '500 g', 'Jautiena'],
            'name with size in middle' => ['Pienas 1.5l UAT', '1.5 l', 'Pienas UAT'],
            'name already normalized comma-space' => ['Alus Švyturys, 0.5 l', '0.5 l', 'Alus Švyturys'],
            'name with unit size vnt' => ['Obuoliai 3 vnt.', '3 vnt', 'Obuoliai'],

            'vnt with trailing dot no space' => ['ZEWA PURE, 42vnt.', '42 vnt', 'ZEWA PURE'],

            'kg trailing dot stripped' => ['x, 2 kg.', '2 kg', 'x, .'],
            'rit trailing dot kept' => ['x, 3 rit.', '3 rit.', 'x'],

            'cm trailing dot stripped' => ['x, 40 cm.', '40 cm', 'x, .'],
            'm trailing dot stripped' => ['x, 2,5 m.', '2.5 m', 'x, .'],
            'mm trailing dot stripped' => ['x, 12 mm.', '12 mm', 'x, .'],
            'km trailing dot stripped' => ['x, 1,5 km.', '1.5 km', 'x, .'],
        ];
    }
}
