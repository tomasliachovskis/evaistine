import { dayHours, fetchHtml, slugify, workTimesFromWeek } from '../_shared.js';

// apotheka.lt/shops lists every pharmacy in a JSON-LD ItemList; each
// pharmacy's own page has a JSON-LD Pharmacy with geo and
// openingHoursSpecification (00:00-00:00 = closed). ~42 pages, fetched one at
// a time.
const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

const jsonLdNodes = html => [...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)]
    .flatMap((m) => {
        try {
            const data = JSON.parse(m[1]);
            return data['@graph'] ?? [data];
        } catch {
            return [];
        }
    });

export default async function apotheka() {
    const list = jsonLdNodes(await fetchHtml('https://www.apotheka.lt/shops'))
        .find(node => node['@type'] === 'ItemList');
    const urls = (list?.itemListElement ?? []).map(entry => entry.item?.url).filter(Boolean);
    const locations = [];

    for (const url of urls) {
        const pharmacy = jsonLdNodes(await fetchHtml(url)).find(node => node['@type'] === 'Pharmacy');
        if (!pharmacy) {
            console.warn(`[Apotheka] no Pharmacy data on ${url}`);
            continue;
        }

        const week = Array(7).fill(null);
        for (const spec of pharmacy.openingHoursSpecification ?? []) {
            for (const day of [].concat(spec.dayOfWeek ?? [])) {
                const i = DAYS.indexOf(String(day).replace('https://schema.org/', ''));
                if (i !== -1) {
                    week[i] = dayHours(spec.opens, spec.closes);
                }
            }
        }

        const address = pharmacy.address?.streetAddress?.trim();
        locations.push({
            externalId: url.split('/').pop(),
            city: pharmacy.address?.addressLocality?.trim() || null,
            address,
            addressSlug: slugify(address ?? ''),
            lat: pharmacy.geo?.latitude ? Number(pharmacy.geo.latitude) : null,
            lng: pharmacy.geo?.longitude ? Number(pharmacy.geo.longitude) : null,
            phones: [pharmacy.telephone].filter(Boolean),
            workTimes: workTimesFromWeek(week),
        });
        await sleep(300);
    }

    return locations;
}
