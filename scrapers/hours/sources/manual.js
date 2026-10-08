import { readFileSync } from 'node:fs';
import { slugify } from '../_shared.js';

// Pharmacies that list one or a few addresses on a contact page (mostly
// online pharmacies): kept by hand in ../manual-locations.json instead of a
// scraper per page.
const FILE = new URL('../manual-locations.json', import.meta.url);

export default async function manual({ store }) {
    const entry = JSON.parse(readFileSync(FILE, 'utf8'))[store];

    if (!entry) {
        throw new Error(`No "${store}" in manual-locations.json`);
    }

    return entry.locations.map(location => ({
        lat: null,
        lng: null,
        phones: [],
        workTimes: [],
        ...location,
        addressSlug: slugify(location.address),
    }));
}
