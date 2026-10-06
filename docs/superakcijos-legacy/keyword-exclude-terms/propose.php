<?php
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Pool;

$pages = json_decode(file_get_contents(storage_path('app/kw/dump.json')), true);
$outFile = storage_path('app/kw/proposals.json');
$done = file_exists($outFile) ? json_decode(file_get_contents($outFile), true) : [];
$key = config('services.openai.api_key');
$model = config('services.openai.model', 'gpt-5-mini');

$system = <<<'P'
Tu tikrini SuperAkcijos.lt keyword puslapį. Puslapis turi H1 ir rodo akcijų prekių sąrašą (names, sunumeruotas). Žmogus, ieškantis šio H1, nori nupirkti konkrečią prekę.

1. off_intent: numeriai prekių, kurios NEatitinka puslapio intencijos (kita prekės rūšis, priedas, aksesuaras, kitos kategorijos prekė, prekė tik su tokio skonio kvapu ir pan.). Pvz. puslapyje "Kavos pupelės" malta kava ir kavos kapsulės yra off_intent; puslapyje "Kava" (bendras) visos kavos rūšys tinka, bet paruošti kavos gėrimai buteliuose ar "kavos skonio" saldainiai – ne. Jei abejoji – prekė tinka (nežymėk). Bendros akcijos, kurios apima ir tinkamą prekę (pvz. "Malta kava X / Kavos pupelės Y" maltos kavos puslapyje), tinka.
2. exclude_terms: trumpos poeilutės (mažosiomis, tiksliai kaip pavadinime, su lietuviškomis raidėmis), kurias pridėjus išmetamos off_intent prekės. Exclude veikia kaip poeilutė pavadinime ARBA brande (brandas nurodytas [laužtiniuose skliaustuose]). SVARBU: exclude terminas neturi pasitaikyti nė vienos tinkamos prekės pavadinime ar brande. Rinkis specifinius žodžius (pvz. "frappuccino", "250 ml" netinka – per bendra; "kakavos" gerai). Nenaudok: akcija, akcijos, nuolaida, iki, maxima, lidl, rimi, norfa.
3. notes: 1 sakinys, kas buvo ne taip.

Jei viskas tinka: {"off_intent": [], "exclude_terms": [], "notes": "ok"}
Atsakyk JSON: {"off_intent": [3, 17], "exclude_terms": ["..."], "notes": "..."}
P;

$todo = array_values(array_filter($pages, fn ($p) => $p['shown'] > 0 && !isset($done[$p['slug']])));
echo count($todo) . " pages to do\n";

foreach (array_chunk($todo, 6) as $batch) {
    $responses = Http::pool(fn (Pool $pool) => array_map(function ($p) use ($pool, $key, $model, $system) {
        $numbered = [];
        foreach ($p['names'] as $i => $n) { $numbered[] = "$i: $n"; }
        return $pool->as($p['slug'])->timeout(300)->withToken($key)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $model,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode([
                    'h1' => $p['h1'] ?: $p['title'],
                    'title' => $p['title'],
                    'current_exclude_terms' => $p['exclude_terms'],
                    'names' => $numbered,
                ], JSON_UNESCAPED_UNICODE)],
            ],
        ]);
    }, $batch));

    foreach ($batch as $p) {
        $r = $responses[$p['slug']] ?? null;
        if (!$r instanceof \Illuminate\Http\Client\Response || !$r->successful()) {
            echo "ERR {$p['slug']}\n";
            continue;
        }
        $parsed = json_decode((string) $r->json('choices.0.message.content'), true);
        if (!is_array($parsed)) { echo "BADJSON {$p['slug']}\n"; continue; }
        $done[$p['slug']] = [
            'off_intent' => array_values(array_map('intval', (array) ($parsed['off_intent'] ?? []))),
            'exclude_terms' => array_values(array_filter(array_map(fn ($t) => mb_strtolower(trim((string) $t)), (array) ($parsed['exclude_terms'] ?? [])))),
            'notes' => (string) ($parsed['notes'] ?? ''),
        ];
        echo "ok {$p['slug']} off=" . count($done[$p['slug']]['off_intent']) . "\n";
    }
    file_put_contents($outFile, json_encode($done, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
echo "done: " . count($done) . "\n";
