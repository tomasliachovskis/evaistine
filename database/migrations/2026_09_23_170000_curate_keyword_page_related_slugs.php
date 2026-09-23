<?php

use App\Support\CacheVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Keyword pages' related_slugs drive both "Susijusios akcijos" and the
     * no-offers empty state's "panašūs pasiūlymai" links
     * (KeywordPageService::buildRelatedPages()/buildAlternativePages()).
     * Before this, 15 pages pointed at slugs that aren't keyword pages at
     * all (old category slugs like "duonos-gaminiai"), and every other page
     * fell back to its coarse category — "mesa-ir-zuvis" mixes fish with
     * pork, "bakaleja" holds 35 unrelated pages — so e.g. "karpis" ended up
     * suggesting sausages, or (with no live siblings) toothbrushes.
     *
     * Each group below is a set of pages that are genuinely interchangeable
     * for a shopper; a page's related_slugs = every other member of every
     * group it belongs to, in listed order, capped at MAX. Only slugs that
     * exist as published keyword pages are written, so this is safe against
     * a DB whose page set differs. Pages in no group keep their category
     * fallback, but any broken slugs they reference are dropped.
     */
    private const MAX = 8;

    private const GROUPS = [
        // Mėsa ir žuvis
        ['zuvis', 'lasisa', 'silke', 'skumbre', 'upetakis', 'karpis', 'dorada', 'sprotai', 'krevetems', 'ikrai', 'saldyta-zuvis', 'saldyti-zuvies-pirsteliai', 'sushi'],
        ['kiauliena', 'kiaulienos-sprandine', 'jautiena', 'jautienos-liezuvis', 'vistienai', 'vistienos-krutinele', 'antis', 'farsas', 'sonine'],
        ['desra', 'desreles', 'kumpis', 'mesos-gaminiai', 'sonine'],

        // Pieno produktai
        ['pienas', 'kefyras', 'jogurtas', 'actimel', 'grietine', 'grietinele', 'sviestas', 'kiausiniai', 'margarinas'],
        ['suris', 'dziugo-suris', 'fermentinis-suris', 'lydytas-suris', 'mozzarella', 'varskei', 'varskes-sureliai'],
        ['kiausiniai', 'pienas', 'sviestas', 'miltai', 'majonezas'],

        // Bakalėja
        ['padazai', 'kecupas', 'majonezas', 'garstycios', 'pomidoru-padazas'],
        ['prieskoniai', 'druska', 'cinamonas', 'santa-maria', 'actas'],
        ['santa-maria', 'tortilijos', 'padazai', 'kukuruzai', 'pupeles', 'prieskoniai'],
        ['aliejus', 'alyvuogiu-aliejus', 'rapsu-aliejus', 'alyvuoges', 'actas'],
        ['makaronai', 'ryziai', 'kruopos', 'grikiai', 'avizos', 'avizu-kose', 'lesiai', 'pupeles', 'miltai'],
        ['konservai', 'pupeles', 'kukuruzai', 'alyvuoges', 'sriubos', 'sprotai', 'pomidoru-padazas'],
        ['medus', 'uogiene', 'nutella', 'cukrus', 'saldikliai'],
        ['miltai', 'cukrus', 'kiausiniai', 'sviestas', 'cinamonas'],

        // Saldumynai ir užkandžiai
        ['sokoladas', 'milka', 'saldainiai', 'ferrero-rocher', 'raffaello', 'nutella', 'batoneliai'],
        ['saldainiai', 'guminukai', 'ledinukai', 'zefyrai', 'sokoladas', 'raffaello'],
        ['sausainiai', 'vafliai', 'meduoliai', 'pyragai', 'krekeriai', 'batoneliai'],
        ['traskuciai', 'pringles', 'krekeriai', 'uzkandziai', 'riesutai'],
        ['riesutai', 'migdolai', 'anakardziai', 'pistacijos', 'razinos'],

        // Vaisiai ir daržovės
        ['bulves', 'morkos', 'svogunai', 'cesnakai', 'sviezi-kopustai', 'agurkai', 'pomidorai', 'paprikos', 'salotos', 'grybai'],
        ['obuoliai', 'bananai', 'apelsinai', 'citrinos', 'vynuoges', 'avokadai'],

        // Duona
        ['duona', 'juoda-duona', 'duona-morta', 'batonas', 'sumustiniu-duona', 'skrudinimo-duona', 'bandeles', 'kruasanai'],

        // Šaldytas maistas
        ['ledai', 'pica', 'saldyti-koldunai', 'saldytos-uogos', 'saldyta-zuvis', 'saldyti-zuvies-pirsteliai'],

        // Gėrimai
        ['kava', 'malta-kava', 'tirpi-kava', 'kavos-kapsules', 'dolce-gusto', 'lavazza', 'paulig', 'dallmayr'],
        ['arbata', 'zalioji-arbata', 'medus', 'sirupai'],
        ['cola', 'pepsi', 'gazuoti-gerimai', 'tonic', 'sultys', 'sirupai', 'redbull', 'monster'],
        ['mineralinis-vanduo', 'vytautas-mineralinis-vanduo', 'sultys', 'gazuoti-gerimai'],
        ['redbull', 'monster', 'cola', 'pepsi'],
        ['alus', 'sidras', 'vynas', 'sampanas', 'kokteiliai', 'tonic'],

        // Buitinė chemija
        ['skalbiklis', 'skalbimo-kapsules', 'skalbimo-milteliai', 'skalbimo-priemones', 'skalbiniu-minkstiklis', 'ringuva-skalbiklis', 'baliklis'],
        ['indu-ploviklis', 'indaploviu-tabletes', 'kempineles', 'popieriniai-ranksluosciai'],
        ['valikliai', 'grindu-valiklis', 'tualeto-valiklis', 'langu-skystis', 'baliklis', 'oro-gaiviklis', 'kempineles', 'siuksliu-maisai'],
        ['tualetinis-popierius', 'popieriniai-ranksluosciai', 'dregnos-serveteles'],

        // Kosmetika ir higiena
        ['dantu-pasta', 'dantu-sepeteliai', 'burnos-higiena', 'burnos-skalavimo-skystis', 'protezu-klijai'],
        ['sampunas', 'duso-zele', 'muilas', 'dezodorantas', 'kuno-kremas', 'skustuvai', 'plauku-dazai'],
        ['veido-kremas', 'veido-kauke', 'micelinis-vanduo', 'ranku-kremas', 'lupu-balzamas', 'kremai-nuo-saules', 'kuno-kremas'],
        ['higieniniai-iklotai', 'tamponai', 'gentle-day', 'dregnos-serveteles'],
        ['prezervatyvai', 'lubrikantas'],

        // Vaikams
        ['sauskelnems', 'pampersams', 'dregnos-serveteles', 'tyreles', 'kudikiu-koses', 'aptamil'],
        ['tyreles', 'kudikiu-koses', 'aptamil', 'sauskelnems'],
        ['zaislai', 'lego'],

        // Gyvūnams
        ['kaciu-maistas', 'sausas-kaciu-maistas', 'konservai-katems', 'whiskas', 'friskies', 'sheba', 'gourmet-gold', 'kaciu-kraikas'],
        ['kaciu-kraikas', 'kraikas', 'tofu-kraikas', 'kaciu-maistas'],
        ['sunu-maistas', 'sausas-sunu-maistas', 'pedigree', 'gyvunu-skanestai', 'galviju-liezuviai'],

        // Namų ūkis
        ['keptuves', 'puodai', 'pjaustymo-lentele', 'virtuves-reikmenys', 'folija', 'kepimo-popierius'],
        ['grilio-prekes', 'anglys', 'texas-anglys', 'folija'],
        ['akumuliatoriams', 'automobiliu-prekems', 'langu-skystis', 'baterijos', 'lemputes'],
        ['baterijos', 'lemputes', 'akumuliatoriams'],
        ['pagalves-akcija', 'patalyne', 'tekstile'],

        // Augalai
        ['geles', 'skintos-geles', 'orchidejos', 'zeme-augalams', 'trasos', 'daigai', 'sodo-prekes'],
    ];

    public function up(): void
    {
        $pages = DB::table('keyword_pages')->get(['id', 'slug', 'is_published', 'related_slugs', 'category_slugs', 'matching_offers_count']);
        $published = $pages->where('is_published', true)->pluck('slug')->flip();

        $related = [];
        foreach (self::GROUPS as $group) {
            foreach ($group as $slug) {
                foreach ($group as $other) {
                    if ($other !== $slug && isset($published[$other])) {
                        $related[$slug][$other] = true;
                    }
                }
            }
        }

        // A short group (e.g. zaislai/lego) is topped up to MAX here with
        // same-category pages, most offers first — buildRelatedPages() uses
        // related_slugs as-is, so the list must already be complete.
        $siblingsByCategory = [];
        foreach ($pages->where('is_published', true)->sortByDesc('matching_offers_count') as $p) {
            foreach ((array) json_decode($p->category_slugs ?? '[]', true) as $category) {
                $siblingsByCategory[$category][] = $p->slug;
            }
        }

        foreach ($pages as $page) {
            if (isset($related[$page->slug])) {
                $slugs = array_keys($related[$page->slug]);
                foreach ((array) json_decode($page->category_slugs ?? '[]', true) as $category) {
                    foreach ($siblingsByCategory[$category] ?? [] as $sibling) {
                        if (count($slugs) >= self::MAX) {
                            break 2;
                        }
                        if ($sibling !== $page->slug && ! in_array($sibling, $slugs, true)) {
                            $slugs[] = $sibling;
                        }
                    }
                }
                $slugs = array_slice($slugs, 0, self::MAX);
            } else {
                // Not curated — just drop references to slugs that aren't
                // published keyword pages (buildRelatedPages() would
                // otherwise use them exclusively and show nothing).
                $current = (array) json_decode($page->related_slugs ?? '[]', true);
                $slugs = array_values(array_filter($current, fn ($s) => isset($published[$s])));
                if ($slugs === $current) {
                    continue;
                }
            }

            DB::table('keyword_pages')->where('id', $page->id)->update([
                'related_slugs' => $slugs === [] ? null : json_encode($slugs),
            ]);
        }

        CacheVersion::bump('keywords');
    }

    public function down(): void
    {
        // The broken pre-migration values aren't worth restoring — clearing
        // curated pages back to null returns them to the category fallback.
        $curated = array_unique(array_merge(...self::GROUPS));
        DB::table('keyword_pages')->whereIn('slug', $curated)->update(['related_slugs' => null]);

        CacheVersion::bump('keywords');
    }
};
