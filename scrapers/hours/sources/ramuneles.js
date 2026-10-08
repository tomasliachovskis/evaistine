import { parse } from 'node-html-parser';
import { cleanText, fetchHtml, jsonAfter, slugify, workTimesFromRows } from '../_shared.js';

// Ramunėlės vaistinė's pharmacies, read from its e-shop 100metu.lt (the same
// company: ramunelesvaistine.lt's "buy online" goes there). The pickup page
// carries clean JSON, `var deliveryStores={"list":[{id, address, work_hours
// (an HTML table), lat, long, phone, city_title, active}]}`. Used for both
// Ramunėlės vaistinė and 100 metų vaistinė.
const CITY_FIXES = { Klaipeda: 'Klaipėda', 'Miroslavo kaimas': 'Miroslavas' };

export default async function ramuneles() {
    const html = await fetchHtml('https://www.100metu.lt/vaistines/57');
    const stores = jsonAfter(html, 'var deliveryStores=')?.list ?? [];

    return stores
        .filter(store => String(store.active) === '1')
        .map((store) => {
            const town = cleanText(store.city_title).split(',')[0].trim();
            const city = CITY_FIXES[town] ?? town;
            const address = cleanText(store.address).replace(/,\s*$/, '');
            const rows = parse(store.work_hours ?? '').querySelectorAll('tr').map((tr) => {
                const cells = tr.querySelectorAll('td').map(td => cleanText(td.text)).filter(Boolean);
                return [cells[0] ?? '', cells.at(-1) ?? ''];
            });

            return {
                externalId: String(store.id),
                city,
                address,
                addressSlug: slugify(address),
                lat: store.lat ? Number(store.lat) : null,
                lng: store.long ? Number(store.long) : null,
                phones: [store.phone].filter(Boolean),
                workTimes: workTimesFromRows(rows),
            };
        });
}
