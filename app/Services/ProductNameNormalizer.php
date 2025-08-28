<?php

namespace App\Services;

class ProductNameNormalizer
{
    private array $brandMappings = [
        'PIEMENĖLIO' => 'Piemenėlio',
        'ROKIŠKIO' => 'Rokiškio',
        'SUSLAVIČIAUS' => 'Suslavičiaus',
        'GASPADORIAUS' => 'Gaspadoriaus',
        'ĄŽUOLYNO' => 'Ąžuolyno',
        'VASAROS' => 'Vasaros',
        'MLEKOVITA' => 'Mlekovita',
        'ELOVENA' => 'Elovena',
        'PIENO ROJUS' => 'Pieno Rojus',
        'NESTEA' => 'Nestea',
        'TEFAL' => 'Tefal',
        'INGCO' => 'Ingco',
        'ONEX' => 'Onex',
        'KINDER' => 'Kinder',
        'SNICKERS' => 'Snickers',
        'TWIX' => 'Twix',
        'SUNRISE' => 'Sunrise',
        'ESPERANZA' => 'Esperanza',
        'KISTENBERG' => 'Kistenberg',
        'TAURAS' => 'Tauras',
        'GAR2' => 'Gar2',
        'YES' => 'Yes',
        'EKONCOPY' => 'Ekoncopy',
        'NYKŠTUKAS' => 'Nykštukas',
        'HEAT&EAT' => 'Heat&Eat',
        'GRISSINI' => 'Grissini',
        'KAI NORISI MĖSOS' => 'Kai Norisi Mėsos',
        'RIMI' => 'Rimi',
        'DVARO' => 'Dvaro',
        'DADU' => 'Dadu',
        'TOSTE' => 'Toste',
        'ŽEMAITIJOS' => 'Žemaitijos',
        'ESKIMO' => 'Eskimo',
        'KARVUTĖ' => 'Karvutė',
        'HASS' => 'Hass',
        'LAVAZZA' => 'Lavazza',
        'FELIX' => 'Felix',
        'AUGA' => 'Auga',
        'GAIDELIS' => 'Gaidelis',
        'NEPTŪNAS' => 'Neptūnas',
        'VILNIAUS DUONA' => 'Vilniaus Duona',
        'SELGA' => 'Selga',
        'POLS' => 'Pols',
        'PIK-NIK' => 'Pik-Nik',
        'SUN365' => 'Sun365',
        'DOBILAS' => 'Dobilas',
        'BILLA PREMIUM' => 'Billa Premium',
        'SUN YAN' => 'Sun Yan',
        'FREE' => 'Free',
        'TUC' => 'Tuc',
        'PLIUS' => 'Plius',
        'CHRIZANTEMA' => 'Chrizantema',
        'KALANKĖ' => 'Kalankė',
        'CIKLAMENAS' => 'Ciklamenas',
        'KARPAŽOLĖ' => 'Karpažolė',
        'KAKTUSAS' => 'Kaktusas',
        'JURGINAI' => 'Jurginai',
        'KARDELIAI' => 'Kardeliai',
        'KARDELIS' => 'Kardelis',
        'ROŽĖS' => 'Rožės',
        'PANEVĖŽIO' => 'Panevėžio',
        'VILKYŠKIŲ' => 'Vilkyškių',
        'LINKĖJIMAI IŠ KAIMO' => 'Linkėjimai iš Kaimo',
        'READY TO EAT' => 'Ready to Eat',
        'WELL DONE' => 'Well Done',
        'RIDO' => 'Rido',
        'KRÖNUNG' => 'Kronung',
        'XXL' => 'XXL',
        'XL' => 'XL',
        'MANGO' => 'Mango',
        'OLANDIŠKAS' => 'Olandiškas',
        'GOUDA' => 'Gouda',
        'HUIZER' => 'Huizer',
        'KAAS-GILDE' => 'Kaas-Gilde',
        'TILSIT' => 'Tilsit',
        'MEMEL RESERVE' => 'Memel Reserve',
        'TWOJ KUBEK' => 'Twoj Kubek',
        'UAT' => 'UAT',
        'MOZZARELLA' => 'Mozzarella',
        'KLASIKA' => 'Klasika',
        'KLASIKINIS' => 'Klasikinis',
        'EXTRA VIRGIN' => 'Extra Virgin',
        'QUALITA ORO' => 'Qualita Oro',
        'CLUB' => 'Club',
        'CLASSIC' => 'Classic',
        'I Love Eco' => 'I Love Eco',
        'Cavendish' => 'Cavendish',
        'Navel' => 'Navel',
        'Hass' => 'Hass',
        'Kronung' => 'Kronung',
    ];

    private array $commonReplacements = [
        'r.s.m.' => 'rieb. s.m.',
        'RSM' => 'rieb. s.m.',
        'vnt.' => 'vnt.',
        'vnt./pak.' => 'vnt./pak.',
        'vnt./rink.' => 'vnt./rink.',
        'pak.' => 'pak.',
        'kg' => 'kg',
        'g' => 'g',
        'l' => 'l',
        'ml' => 'ml',
        'art.' => 'art.',
        'W' => 'W',
        '%' => '%',
        'Van.' => 'Vanilinis',
        'Glaist.' => 'Glaistytas',
        'Šok.' => 'Šokoladinis',
        'Nat.' => 'Natūralus',
        'Ekol.' => 'Ekologiškas',
        'Konserv.' => 'Konservuotas',
        'Klasikiniai' => 'Klasikiniai',
        'I Love Eco' => 'I Love Eco',
        'Cavendish' => 'Cavendish',
        'Navel' => 'Navel',
        'Extra Virgin' => 'Extra Virgin',
        'QUALITA ORO' => 'Qualita Oro',
        'CLUB' => 'Club',
        'CLASSIC' => 'Classic',
    ];

    private array $removePatterns = [
        '/\s*,\s*$/',
        '/\s*\([^)]*\)\s*$/',
        '/\s*\[[^\]]*\]\s*$/',
        '/\s*-\s*$/',
        '/\s*"\s*$/',
    ];

    private array $abbreviationExpansions = [
        '/\bVan\.\s+/' => 'Vanilinis ',
        '/\bGlaist\.\s+/' => 'Glaistytas ',
        '/\bŠok\.\s+/' => 'Šokoladinis ',
        '/\bNat\.\s+/' => 'Natūralus ',
        '/\bEkol\.\s+/' => 'Ekologiškas ',
        '/\bKonserv\.\s+/' => 'Konservuotas ',
        '/\bplombyr\.\s+/' => 'plombyras ',
        '/\bvanil\.\s+/' => 'vanilinis ',
        '/\bvafl\.\s+/' => 'vafliniame ',
        '/\bp\.\s+/' => 'puodelyje ',
        '/\bGl\.\s+/' => 'Glaistytas ',
        '/\bAvinžirniai\s+/' => 'Avinžirniai ',
        '/\bSviestinės\s+/' => 'Sviestinės ',
        '/\bpusrieb\.\s+/' => 'pusrieb. ',
        '/\bnegaz\.\s+/' => 'negazuotas ',
        '/\bsir\.\s+/' => 'sirupo ',
        '/\bsk\.\s+/' => 'skonio ',
        '/\bvalg\.\s+/' => 'valgomasis ',
        '/\bledai\s+/' => 'ledai ',
        '/\bmišk\.\s+/' => 'miško ',
        '/\buog\.\s+/' => 'uogų ',
        '/\bįd\.\s+/' => 'įdaru ',
        '/\bmigd\.\s+/' => 'migdolų ',
        '/\bSUMUŠTINIŲ\s+/' => 'Sumuštinių ',
    ];

    private array $measurementPatterns = [
        '/\b(\d+)\s*kg\b/i' => '$1 kg',
        '/\b(\d+)\s*g\b/i' => '$1 g',
        '/\b(\d+)\s*l\b/i' => '$1 l',
        '/\b(\d+)\s*ml\b/i' => '$1 ml',
        '/\b(\d+)\s*vnt\b/i' => '$1 vnt.',
        '/\b(\d+)\s*pak\b/i' => '$1 pak.',
        '/\b(\d+)\+?\s*mm\b/i' => '$1+ mm',
        '/\b(\d+)\s*,\s*(\d+)\s*%\s*rieb\b/i' => '$1,$2% rieb.',
        '/\b(\d+)\s*%\s*rieb\b(?!\.)/i' => '$1% rieb.',
        '/\b(\d+)\s*%\s*RSM\b/i' => '$1% rieb. s.m.',
        '/\b(\d+)\s*%\s*r\.s\.m\.\b/i' => '$1% rieb. s.m.',
        '/\b(\d+)\s*x\s*(\d+)\s*g\b/i' => '$1x$2 g',
        '/\b(\d+)\s*x\s*(\d+)\s*vnt\b/i' => '$1x$2 vnt.',
    ];

    public function normalize(string $productName): string
    {
        $normalized = $productName;

        $normalized = $this->normalizeBrands($normalized);
        $normalized = $this->expandAbbreviations($normalized);
        $normalized = $this->normalizeCommonTerms($normalized);
        $normalized = $this->cleanExtraSpaces($normalized);
        $normalized = $this->removeTrailingPatterns($normalized);
        $normalized = $this->normalizePunctuation($normalized);
        $normalized = $this->standardizeMeasurements($normalized);

        return trim($normalized);
    }

    private function normalizeBrands(string $name): string
    {
        foreach ($this->brandMappings as $incorrect => $correct) {
            $name = str_replace($incorrect, $correct, $name);
        }
        return $name;
    }

    private function expandAbbreviations(string $name): string
    {
        foreach ($this->abbreviationExpansions as $pattern => $replacement) {
            $name = preg_replace($pattern, $replacement, $name);
        }
        return $name;
    }

    private function normalizeCommonTerms(string $name): string
    {
        foreach ($this->commonReplacements as $incorrect => $correct) {
            $name = preg_replace('/\b' . preg_quote($incorrect, '/') . '\b/i', $correct, $name);
        }
        return $name;
    }

    private function cleanExtraSpaces(string $name): string
    {
        $name = preg_replace('/\s+/', ' ', $name);
        $name = preg_replace('/\s*,\s*/', ', ', $name);
        $name = preg_replace('/\s*-\s*/', ' - ', $name);
        $name = preg_replace('/\s*\/\s*/', '/', $name);
        $name = preg_replace('/\s*%\s*(?!\w)/', '%', $name);
        $name = preg_replace('/\s*%\s+(?=\w)/', '% ', $name);
        $name = preg_replace('/\s*\+\s*/', '+', $name);
        $name = preg_replace('/\s*\(\s*/', ' (', $name);
        $name = preg_replace('/\s*\)\s*/', ') ', $name);
        return $name;
    }

    private function removeTrailingPatterns(string $name): string
    {
        foreach ($this->removePatterns as $pattern) {
            $name = preg_replace($pattern, '', $name);
        }
        return $name;
    }

    private function normalizePunctuation(string $name): string
    {
        $name = preg_replace('/\s*,\s*,\s*/', ', ', $name);
        $name = preg_replace('/,\s*$/', '', $name);
        return $name;
    }

    private function standardizeMeasurements(string $name): string
    {
        foreach ($this->measurementPatterns as $pattern => $replacement) {
            $name = preg_replace($pattern, $replacement, $name);
        }
        return $name;
    }
}
