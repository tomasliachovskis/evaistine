import { fetchHtml, jsonAfter, isRegionPart, municipalityCentre, phoneFrom, rowsFromLine, slugify, workTimesFromRows } from '../_shared.js';

// N vaistinė (nvaistine.lt/vaistines/): every pharmacy is in the page's map
// data, `var _markers_data_main = [{id, lat, lang, workingTime, contacts,
// fullAddress}]`. workingTime reads "I-V  8 - 19 VI  8 - 16 VII  10 - 15".
// fullAddress is "street, town[, municipality]"; nine give only the
// municipality ("Tauragės g. 1, Šilalės r. sav."), which then maps to its
// centre town.
//
// Towns the address leaves out entirely, by marker id (checked against the
// coordinates).
const TOWN_BY_ID = { 136: 'Palanga' };

export default async function nvaistine() {
    const html = await fetchHtml('https://www.nvaistine.lt/vaistines/');
    const markers = jsonAfter(html, 'var _markers_data_main = ') ?? [];

    return markers.map((marker) => {
        // Notes like "(yra skiepų kabinetas)" ride along in the address.
        const [street, ...rest] = marker.fullAddress.replace(/\([^)]*\)/g, '')
            .split(',').map(part => part.trim().replace(/\s+/g, ' ')).filter(Boolean);
        const town = rest.find(part => !isRegionPart(part));
        const region = rest.find(part => isRegionPart(part));
        const city = town ?? (region ? municipalityCentre(region) : null) ?? TOWN_BY_ID[marker.id] ?? null;

        return {
            externalId: String(marker.id),
            city,
            address: street,
            addressSlug: slugify(street),
            lat: marker.lat ? Number(marker.lat) : null,
            lng: marker.lang ? Number(marker.lang) : null,
            phones: [phoneFrom(marker.contacts)].filter(Boolean),
            workTimes: workTimesFromRows(rowsFromLine(marker.workingTime)),
        };
    });
}
