import { dayHours, fetchHtml, isRegionPart, slugify, workTimesFromWeek } from '../_shared.js';

// benu.lt/vaistiniu-paieska embeds every pharmacy as `pharmacies = {...}` JSON
// (ID, address "Medeinos g. 8, Vilnius", region, phone, coordinates,
// workHours by English weekday; a missing day is closed). "00:00 - 00:00"
// is a round-the-clock pharmacy (status "opened", the page's 24/7 filter),
// not a closed one.
const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

export default async function benu() {
    const html = await fetchHtml('https://www.benu.lt/vaistiniu-paieska');
    const marker = "pharmacies = {\"";
    const at = html.indexOf(marker);
    if (at === -1) {
        throw new Error('pharmacies JSON not found');
    }
    const start = at + 'pharmacies = '.length;
    const end = html.indexOf(";\n", start);
    const pharmacies = Object.values(JSON.parse(html.slice(start, end).replace(/';?\s*$/, '')));

    return pharmacies
        .filter(p => p.eshop === 'LT' && !p.hideInPharmacyFinder)
        .map((p) => {
            // After the comma is sometimes a shopping centre ("Hyper RIMI"),
            // so the town comes from `region`; when that is a municipality
            // ("Kauno raj."), the town is the address part after the street.
            const [street, ...rest] = p.address.split(',').map(part => part.trim());
            const hours = p.workHours ?? {};
            // No hours on any day means unknown, not closed all week.
            const known = DAYS.some(day => hours[day]);
            return {
                externalId: String(p.ID),
                city: isRegionPart((p.region ?? '').trim())
                    ? rest.find(part => !isRegionPart(part)) ?? p.region.trim()
                    : (p.region ?? '').trim() || rest.at(-1),
                address: street,
                addressSlug: slugify(street),
                lat: p.latitude ? Number(p.latitude) : null,
                lng: p.longitude ? Number(p.longitude) : null,
                phones: [p.phone].filter(Boolean),
                workTimes: workTimesFromWeek(DAYS.map((day) => {
                    if (!hours[day]) {
                        return known ? 'Nedirba' : null;
                    }
                    const [open, close] = String(hours[day]).split(/\s*-\s*/);
                    return open === '00:00' && close === '00:00' ? '00:00-24:00' : dayHours(open, close);
                })),
            };
        });
}
