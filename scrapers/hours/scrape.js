import { submitLocations } from './_shared.js';
import nuolaidos from './sources/nuolaidos.js';
import nvaistine from './sources/nvaistine.js';
import manoVaistine from './sources/mano-vaistine.js';
import ramuneles from './sources/ramuneles.js';
import manual from './sources/manual.js';
import eurovaistine from './sources/eurovaistine.js';
import gintarine from './sources/gintarine.js';
import camelia from './sources/camelia.js';
import benu from './sources/benu.js';
import apotheka from './sources/apotheka.js';

// Pharmacy addresses and opening hours, one source per pharmacy. Store names
// must match stores.name; the list mirrors config/hours_scrapers.php (kept in
// sync by hand: scrapers don't read Laravel config).
//   node scrapers/hours/scrape.js            every pharmacy
//   node scrapers/hours/scrape.js camelia    one, by name or slug
//   SCRAPER_DRY_RUN=1 ...                    print, don't POST
// The big chains moved from nuolaidos.lt to their own sites on 2026-10-08
// (nuolaidos.lt kept closed pharmacies, e.g. Camelia "V. Krėvės pr. 97H").
// nuolaidos.js stays as a fallback source.
const SOURCES = {
    nuolaidos: { run: nuolaidos, label: 'nuolaidos.lt' },
    eurovaistine: { run: eurovaistine, label: 'eurovaistine.lt' },
    gintarine: { run: gintarine, label: 'gintarine.lt' },
    camelia: { run: camelia, label: 'api.camelia.lt' },
    benu: { run: benu, label: 'benu.lt' },
    apotheka: { run: apotheka, label: 'apotheka.lt' },
    nvaistine: { run: nvaistine, label: 'nvaistine.lt' },
    'mano-vaistine': { run: manoVaistine, label: 'manovaistine.lt' },
    ramuneles: { run: ramuneles, label: '100metu.lt' },
    manual: { run: manual, label: 'manual' },
};

const STORES = [
    { store: 'Eurovaistinė', slug: 'eurovaistine', source: 'eurovaistine' },
    { store: 'Gintarinė vaistinė', slug: 'gintarine-vaistine', source: 'gintarine' },
    { store: 'Camelia', slug: 'camelia', source: 'camelia' },
    { store: 'Benu vaistinė', slug: 'benu-vaistine', source: 'benu' },
    { store: 'Apotheka', slug: 'apotheka', source: 'apotheka' },
    { store: 'N vaistinė', slug: 'nvaistine', source: 'nvaistine' },
    { store: 'Mano vaistinė', slug: 'mano-vaistine', source: 'mano-vaistine' },
    { store: 'Ramunėlės vaistinė', slug: 'ramuneles-vaistine', source: 'ramuneles' },
    { store: '100 metų vaistinė', slug: '100-metu-vaistine', source: 'ramuneles' },
    { store: 'Ąžuolyno vaistinė', slug: 'azuolyno-vaistine', source: 'manual' },
    { store: 'LSMU vaistinė', slug: 'lsmu-vaistine', source: 'manual' },
    { store: 'Universiteto vaistinė', slug: 'universiteto-vaistine', source: 'manual' },
    { store: 'Piliulė', slug: 'piliule', source: 'manual' },
    { store: 'Rx vaistinė', slug: 'rx-vaistine', source: 'manual' },
    { store: 'InternetineVaistine.lt', slug: 'internetine-vaistine', source: 'manual' },
];

const DRY_RUN = Boolean(process.env.SCRAPER_DRY_RUN);
const arg = process.argv[2]?.toLowerCase();
const storesToRun = arg
    ? STORES.filter(s => s.store.toLowerCase() === arg || s.slug === arg)
    : STORES;

if (arg && storesToRun.length === 0) {
    console.error(`Unknown store "${process.argv[2]}". Known: ${STORES.map(s => s.store).join(', ')}`);
    process.exit(1);
}

let failed = false;

for (const entry of storesToRun) {
    const { store, source } = entry;

    try {
        const locations = await SOURCES[source].run(entry);
        const incomplete = locations.filter(l => !l.city || !l.address);

        if (incomplete.length > 0) {
            console.warn(`[${store}] ${incomplete.length} without city/address, skipped:`, incomplete.map(l => l.externalId).join(', '));
        }

        const complete = locations.filter(l => l.city && l.address);

        if (complete.length === 0) {
            console.log(`[${store}] 0 locations found, skipping`);
            continue;
        }

        if (DRY_RUN) {
            console.log(`[${store}] ${complete.length} locations (dry run)`);
            console.log(JSON.stringify(complete.slice(0, 3), null, 2));
            continue;
        }

        const result = await submitLocations(store, SOURCES[source].label, complete);
        console.log(`[${store}] ${complete.length} scraped -> created=${result.created} updated=${result.updated} deactivated=${result.deactivated}`);
    } catch (error) {
        failed = true;
        console.error(`[${store}] failed: ${error.message}`);
    }
}

process.exit(failed ? 1 : 0);
