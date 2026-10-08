import { dayHours, fetchHtml, isRegionPart, literalAt, slugify, titleCaseTown, workTimesFromWeek } from '../_shared.js';

// eurovaistine.lt/vaistines is a Next.js page: the full pharmacy list sits in
// its flight data (escaped JSON inside script tags), while the HTML renders
// only a dozen cards. Each pharmacy is an object with code, city (in
// capitals), address, phoneNumber, lat/lng, workingHours keyed "1".."7"
// (Monday first; "-" = closed) and temporarilyClosed. Temporarily closed
// ones are left out until they reopen.
export default async function eurovaistine() {
    const text = (await fetchHtml('https://www.eurovaistine.lt/vaistines'))
        .replace(/\\"/g, '"')
        .replace(/\\\\/g, '\\');
    const pharmacies = new Map();

    for (const match of text.matchAll(/\{"(?:differentWorkingHours|code)":/g)) {
        let item;
        try {
            item = JSON.parse(literalAt(text, match.index));
        } catch {
            continue;
        }
        if (item.code && item.address && item.workingHours && !pharmacies.has(item.code)) {
            pharmacies.set(item.code, item);
        }
    }

    return [...pharmacies.values()]
        .filter(p => !p.temporarilyClosed)
        .map((p) => {
            // A few give a municipality as the city ("KAUNO RAJ.") and the
            // village after the street ("Automagistralės g. 4, Giraitės k.").
            const [street, ...rest] = p.address.split(',').map(part => part.trim()).filter(Boolean);
            const city = titleCaseTown(p.city);
            return {
            externalId: p.code,
            city: isRegionPart(city) ? rest[0] ?? city : city,
            address: isRegionPart(city) ? street : p.address.trim(),
            addressSlug: slugify(isRegionPart(city) ? street : p.address),
            lat: p.lat ? Number(p.lat) : null,
            lng: p.lng ? Number(p.lng) : null,
            phones: [p.phoneNumber].filter(Boolean),
            workTimes: workTimesFromWeek([1, 2, 3, 4, 5, 6, 7].map((day) => {
                const hours = p.workingHours[day];
                return hours ? dayHours(hours.startTime, hours.endTime) : null;
            })),
            };
        });
}
