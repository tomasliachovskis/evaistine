import { buildRow, createPoster, currentWeek, fetchJson, MAX_PAGES, sleep, validEan } from './lib/pharmacy.js';

const BASE_URL = 'https://www.100metu.lt';
const STORE = '100 metų vaistinė';

// EasyWeb with an AngularJS catalog. The category pages load their products
// from this JSON endpoint; cat=0 is the whole catalog, and it pages with the
// angular-paginate-anything headers ("Range-Unit: items", "Range: 0-199"),
// not with a query parameter. Each product carries its barcode, prescription
// flag, main category and prices. Ramunėlės vaistinė's site links here for
// online shopping (same company), so it has no e-shop scraper of its own.
const DATA_URL = `${BASE_URL}/prekiu-katalogas/5/cat-0/productsdata`;
const PAGE_SIZE = 200;

// `retail_price` is the regular price; while `is_discount` is set,
// `discount_price` is the lower one everybody pays.
const toRow = (product, dates) => buildRow({
    store: STORE,
    name: product.title,
    url: `${BASE_URL}${product.view_url}`,
    category: product.main_category_title || null,
    price: product.is_discount ? Number(product.discount_price) : Number(product.retail_price),
    regular: product.is_discount ? Number(product.retail_price) : null,
    ean: validEan(product.barcode) ?? validEan(product.barcode2),
    image: product.img_listimage || product.img_preview || '',
    dates,
});

(async () => {
    const dates = currentWeek();
    console.log(`Price validity week: ${dates.start_at} - ${dates.end_at}`);

    const poster = createPoster(STORE);
    let skippedPrescription = 0;
    let total = 0;
    let currentPage = 0;
    let pageCount = 1;

    do {
        const from = currentPage * PAGE_SIZE;
        const response = await fetchJson(DATA_URL, { cat: 0, min_price: 0, sort: 'atitle' }, {
            'Range-Unit': 'items',
            Range: `${from}-${from + PAGE_SIZE - 1}`,
        });
        if (!Array.isArray(response?.products)) {
            console.log(`Giving up on page ${currentPage + 1}.`);
            break;
        }
        total = Number(response.total) || 0;
        pageCount = Math.min(Math.ceil(total / PAGE_SIZE) || 1, MAX_PAGES);

        const rows = [];
        for (const product of response.products) {
            // Prescription medicines are listed (reserve only) but not sold.
            if (product.prescription === '1' || product.prescription === 1) {
                skippedPrescription++;
                continue;
            }
            if (Number(product.retail_price) > 0) {
                rows.push(toRow(product, dates));
            }
        }

        await poster.post(rows, `page ${currentPage + 1}/${pageCount}`);
        currentPage++;
        await sleep();
    } while (currentPage < pageCount);

    poster.summary(`Site reports ${total} products; skipped ${skippedPrescription} prescription.`);
})();
