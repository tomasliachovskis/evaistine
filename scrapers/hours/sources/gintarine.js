import { cleanText, fetchHtml, isRegionPart, literalAt, municipalityCentre, slugify, workTimesFromRows } from '../_shared.js';

// gintarine.lt/vaistines embeds its map data as a JavaScript object (unquoted
// keys, !0/!1), with `Destinations:[...]` (Id, CityId, Name, PrimaryInfo
// "street[, town]", Latitude/Longitude, AvailabilityHours [{Days, Availability}])
// and `Cities:[{Id, Name}]`. The list also holds N vaistinė's pharmacies
// ("Norfos vaistinė", Gintarinė runs their e-shop); only entries named
// "Gintarinė vaistinė..." are this chain's.
const literal = (html, key) => {
    const at = html.indexOf(`${key}:[`);
    if (at === -1) {
        throw new Error(`${key} not found`);
    }
    const js = literalAt(html, at + key.length + 1);
    return JSON.parse(js
        .replace(/([{,])([A-Za-z_]\w*):/g, '$1"$2":')
        .replace(/!0\b/g, 'true')
        .replace(/!1\b/g, 'false'));
};

export default async function gintarine() {
    const html = await fetchHtml('https://www.gintarine.lt/vaistines');
    const cities = new Map(literal(html, 'Cities').map(city => [city.Id, city.Name]));

    return literal(html, 'Destinations')
        .filter(d => /gintarin/i.test(d.Name))
        .map((d) => {
            // Notes ride along: "Plungė (yra skiepų kabinetas)", "(PC IKI)".
            const [street, ...rest] = cleanText(d.PrimaryInfo).replace(/\([^)]*\)/g, '')
                .split(',').map(part => part.trim().replace(/\s+/g, ' ')).filter(Boolean);
            let city = rest.find(part => !isRegionPart(part)) ?? cities.get(d.CityId) ?? null;
            if (city && isRegionPart(city)) {
                city = municipalityCentre(city);
            }
            const phone = (d.SecondaryInfoLines ?? []).find(line => /^\+?\d[\d\s]{6,}$/.test(String(line).trim()));

            return {
                externalId: String(d.Id),
                city,
                address: street,
                addressSlug: slugify(street ?? ''),
                lat: d.Latitude ? Number(d.Latitude) : null,
                lng: d.Longitude ? Number(d.Longitude) : null,
                phones: [phone].filter(Boolean),
                workTimes: workTimesFromRows((d.AvailabilityHours ?? []).map(a => [a.Days, a.Availability])),
            };
        });
}
