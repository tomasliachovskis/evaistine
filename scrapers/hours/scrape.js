import { extractWorkingHours, submitLocations } from './_shared.js';

// Mirrors config/hours_scrapers.php — kept in sync manually (no shared
// PHP/JS config mechanism exists in this repo; scrapers/*.js already
// duplicate their own store lists rather than reading Laravel config).
const STORES = [
    { store: 'Maxima', slug: 'maxima' },
    { store: 'Iki', slug: 'iki' },
    { store: 'Lidl', slug: 'lidl' },
    { store: 'Norfa', slug: 'norfa' },
    { store: 'Rimi', slug: 'rimi' },
    { store: 'Aibė', slug: 'aibe' },
    { store: 'Šilas', slug: 'silas' },
    { store: 'Čia', slug: 'cia' },
    { store: 'Grustė', slug: 'gruste' },
    { store: 'Express Market', slug: 'express-market' },
    { store: 'Koops', slug: 'koops' },
    { store: 'Gulbelė', slug: 'gulbele' },
    { store: 'Kubas', slug: 'kubas' },
    { store: 'Vynoteka', slug: 'vynoteka' },
    { store: 'Thomas Philipps', slug: 'thomas-philipps' },
    { store: 'ePromo', slug: 'epromo' },
    { store: 'Apotheka', slug: 'apotheka' },
    { store: 'Benu vaistinė', slug: 'benu-vaistine' },
    { store: 'Bikuva', slug: 'bikuva' },
    { store: 'Camelia', slug: 'camelia' },
    { store: 'Elimart', slug: 'elimart' },
    { store: 'Ermitažas', slug: 'ermitazas' },
    { store: 'Eurokos', slug: 'eurokos' },
    { store: 'Eurovaistinė', slug: 'eurovaistine' },
    { store: 'Gintarinė vaistinė', slug: 'gintarine-vaistine' },
    { store: 'Jupoja', slug: 'jupoja' },
    { store: 'Jysk', slug: 'jysk' },
    { store: 'Moki Veži', slug: 'moki-vezi' },
    { store: 'Pepco', slug: 'pepco' },
    { store: 'Promo Cash&Carry', slug: 'promo-cash-carry' },
    { store: 'Ramunėlės vaistinė', slug: 'ramuneles-vaistine' },
    { store: 'Senukai', slug: 'senukai' },
    { store: 'TECHasas', slug: 'techasas' },
    { store: 'Technorama', slug: 'technorama' },
    { store: 'Douglas', slug: 'douglas' },
    { store: 'Ikea', slug: 'ikea' },
    { store: 'Lytagra', slug: 'lytagra' },
    { store: 'Mary Kay', slug: 'mary-kay' },
    { store: 'Švaros Prekės', slug: 'svaros-prekes' },
];

const arg = process.argv[2]?.toLowerCase();
const storesToRun = arg
    ? STORES.filter(s => s.store.toLowerCase() === arg || s.slug === arg)
    : STORES;

if (arg && storesToRun.length === 0) {
    console.error(`Unknown store "${process.argv[2]}". Known: ${STORES.map(s => s.store).join(', ')}`);
    process.exit(1);
}

for (const { store, slug } of storesToRun) {
    try {
        const locations = await extractWorkingHours(slug);

        if (locations.length === 0) {
            console.log(`[${store}] 0 locations found, skipping`);
            continue;
        }

        const result = await submitLocations(store, locations);
        console.log(`[${store}] ${locations.length} scraped -> created=${result.created} updated=${result.updated} deactivated=${result.deactivated}`);
    } catch (error) {
        console.error(`[${store}] failed: ${error.message}`);
    }
}
