import { dayHours, slugify, USER_AGENT, workTimesFromWeek } from '../_shared.js';

// Camelia's own pickup-point API (the same api.camelia.lt the product scraper
// uses). It lists ~3 600 points; pharmacies are the ones with a type icon
// (mainTypeImage), and `enabled` false means closed (e.g. "V. Krėvės pr. 97H",
// still listed on nuolaidos.lt). Hours per weekday in details.working_hours,
// null = closed.
const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII'];

export default async function camelia() {
    const response = await fetch('https://api.camelia.lt/api/v2/shop/locations?itemsPerPage=5000', {
        headers: { 'User-Agent': USER_AGENT, Accept: 'application/json' },
        signal: AbortSignal.timeout(60000),
    });
    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const { data } = await response.json();

    return data
        // Without a street are internal entries ("Rezervinis", "Testas").
        .filter(point => point.mainTypeImage && point.enabled && point.streetName)
        .map((point) => {
            const hours = point.details?.working_hours ?? {};
            return {
                externalId: String(point.id),
                city: point.city,
                address: point.streetName,
                addressSlug: slugify(point.streetName ?? ''),
                lat: point.latitude ? Number(point.latitude) : null,
                lng: point.longitude ? Number(point.longitude) : null,
                phones: [point.phoneNumber].filter(Boolean),
                workTimes: workTimesFromWeek(ROMAN.map(day => (hours[day] ? dayHours(hours[day].from, hours[day].to) : 'Nedirba'))),
            };
        });
}
