import { parse } from 'node-html-parser';
import { cleanText, fetchHtml, isRegionPart, municipalityCentre, phoneFrom, slugify, workTimesFromRows } from '../_shared.js';

// Mano vaistinė (manovaistine.lt/visos-vaistines): a list of independent
// member pharmacies, grouped under a town or municipality heading. Each card
// has a free-form address line ("Balbieriškis, Prienų r. sav., Vilniaus g.
// 107, LT-59239", "Margirio g. 1C, Ringaudai, 53335 Kauno rajonas"), a phone
// link and an hours table. No coordinates. The page's HTML is badly nested
// (a DOM parse loses about a quarter of the cards), so the raw HTML is cut
// at each card and heading, and only each card's own slice is parsed.
const STREET = /\b(g|pr|al|pl|skg|a|kel|tak)\.\s*\d|\bg\.\d/i;

export default async function manoVaistine() {
    const html = await fetchHtml('https://www.manovaistine.lt/visos-vaistines');
    const marks = [...html.matchAll(/<div class="shops-list-header-title">([^<]*)<\/div>|<div class="shops-item">/g)];
    const locations = [];
    let heading = '';

    marks.forEach((mark, i) => {
        if (mark[1] !== undefined) {
            heading = cleanText(mark[1]);
            return;
        }

        const item = parse(html.slice(mark.index, marks[i + 1]?.index ?? html.length));
        const lines = item.querySelectorAll('li');
        const addressLine = cleanText(lines[0]?.text);
        const parts = addressLine.split(',')
            .map(part => part.replace(/\b(LT-?)?\d{5}\b/g, '').trim())
            .filter(Boolean);
        const street = parts.find(part => STREET.test(part));
        const towns = parts.filter(part => part !== street && !isRegionPart(part));
        const city = towns[0]
            ?? (isRegionPart(heading) ? municipalityCentre(heading) : heading);

        if (!street || !city) {
            console.warn(`[Mano vaistinė] skipped, can't read the address: "${addressLine}"`);
            return;
        }

        // Some cards leave the last </tr> open, which a DOM parse drops, so
        // the rows are read from the markup.
        const table = html.slice(mark.index, marks[i + 1]?.index ?? html.length).split('<table')[1] ?? '';
        const rows = table.split(/<tr[\s>]/).slice(1).map((tr) => {
            const cells = [...tr.matchAll(/<td[^>]*>([\s\S]*?)<\/td>/g)].map(m => cleanText(m[1])).filter(Boolean);
            return [cells[0] ?? '', cells.at(-1) ?? ''];
        });

        locations.push({
            externalId: slugify(`${city} ${street}`),
            city,
            address: street,
            addressSlug: slugify(street),
            lat: null,
            lng: null,
            phones: [phoneFrom(item.querySelector('a[href^="tel:"]')?.text ?? '')].filter(Boolean),
            workTimes: workTimesFromRows(rows),
        });
    });

    return locations;
}
