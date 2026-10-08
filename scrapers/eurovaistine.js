import { buildRow, createPoster, currentWeek, fetchJson, gtinCheckDigit, MAX_PAGES, onlySelected, sleep } from './lib/pharmacy.js';

const BASE_URL = 'https://www.eurovaistine.lt';
const STORE = 'Eurovaistinė';

// The category pages only server-render their first ~28 products and ignore
// ?page=; the site itself pages through this JSON endpoint (Luigi's Box
// behind Eurovaistinė's own API), which needs no browser, cookie or token.
const LISTING_URL = 'https://api.eurovaistine.lt/eshop-api/luigi/search/api/taxon/render';

// Full catalog of the non-prescription roots. Left out on purpose:
// `vaistai-skirti-gydytojo` (prescription medicines, it returns a drugList
// and no products anyway), and `tik-internete` plus the ~4000 brand taxons,
// which are only other collections of the same products.
const ROOTS = [
    'vaistai-nereceptiniai',
    'vitaminai-ir-maisto-papildai',
    'kosmetika',
    'medicinos-ir-slaugos-prekes',
    'vaikui-ir-mamai',
];

// Second-level taxons that are themes, not product types: they overlap with
// the product-type ones (a Korean face cream is also under "Veidui"). They're
// scraped last, so a product seen in both keeps its product-type category.
const THEME_TAXONS = [
    'kosmetika/dermokosmetika',
    'kosmetika/korejietiska-kosmetika',
    'kosmetika/kelionine-kosmetika',
    'kosmetika/paaugliu-kosmetika',
    'kosmetika/vyru-kosmetika',
    'vitaminai-ir-maisto-papildai/seimai',
    'vaikui-ir-mamai/gimdyves-krepselis',
    'vaikui-ir-mamai/naujagimio-kraitelis',
];

// There's no EAN field; `sku` is either an internal 6–7 digit code (no EAN
// then) or 12 digits. Those 12 digits are the EAN-13 without its check digit
// (most fail a GTIN check as they are), except US UPC-A codes, which start
// with 0 and are complete (Swanson 087614020341). ProcessDiscounts accepts a
// 12-digit value as is, so the conversion has to happen here.
const toEan = (sku) => {
    const code = String(sku ?? '');
    if (!/^\d{12}$/.test(code)) {
        return null;
    }
    if (code.startsWith('0') && gtinCheckDigit(code.slice(0, 11)) === code[11]) {
        return `0${code}`;
    }
    const ean = code + gtinCheckDigit(code);
    // In-store EAN-13s (prefix 2) can't match another pharmacy's product.
    return ean.startsWith('2') ? null : ean;
};

// `price.price` is the current promo price (usually the loyalty-club price,
// `discount.isLoyalty`), null when there's no promo; `regularPrice` is the
// price everyone pays. `promoTitle` holds a promo's condition, e.g.
// "Galioja perkant 2 bet kurias prekes.". Prices are in cents.
const toRow = (variant, category, dates) => buildRow({
    store: STORE,
    name: variant.name,
    url: `${BASE_URL}/${variant.slug}`,
    category,
    price: variant.price?.price ? variant.price.price / 100 : null,
    regular: variant.price?.regularPrice ? variant.price.regularPrice / 100 : null,
    card: variant.discount?.isLoyalty,
    condition: (variant.discount?.promoTitle ?? '').trim() || null,
    brand: variant.brand,
    ean: toEan(variant.sku),
    image: variant.images?.ev_large?.[0],
    dates,
});

const fetchListing = async (slug, page) => {
    const response = await fetchJson(LISTING_URL, { slug, page });
    const data = response?.data;
    return data?.pagination && Array.isArray(data.products) ? data : null;
};

(async () => {
    const dates = currentWeek();
    console.log(`Price validity week: ${dates.start_at} - ${dates.end_at}`);

    const poster = createPoster(STORE);
    const seen = new Set();
    let skippedPrescription = 0;
    let expectedTotal = 0;

    for (const root of onlySelected(ROOTS)) {
        const first = await fetchListing(root, 1);
        if (!first) {
            console.log(`Giving up on root ${root}.`);
            continue;
        }
        await sleep();

        const rootName = first.pageInformation?.pageHeading ?? root;
        expectedTotal += first.pagination.total_items ?? 0;

        // Product-type taxons first, themes last, then the root itself for any
        // product filed under no second-level taxon (already-seen ones skip).
        const taxons = (first.taxonCount ?? [])
            .map(taxon => ({ slug: taxon.url, category: `${rootName}/${taxon.name}` }))
            .sort((a, b) => THEME_TAXONS.includes(a.slug) - THEME_TAXONS.includes(b.slug));
        taxons.push({ slug: root, category: rootName });

        for (const taxon of taxons) {
            let currentPage = 1;
            let pageCount = 1;

            do {
                const listing = await fetchListing(taxon.slug, currentPage);
                if (!listing) {
                    console.log(`Giving up on ${taxon.slug} page ${currentPage}.`);
                    break;
                }
                pageCount = Math.min(listing.pagination.total_pages || 1, MAX_PAGES);

                // `products` also holds banner entries (type: "banner").
                const rows = [];
                for (const product of listing.products) {
                    for (const variant of product.variants ?? []) {
                        if (seen.has(variant.id)) {
                            continue;
                        }
                        seen.add(variant.id);
                        if (variant.otherOptions?.isPrescription) {
                            skippedPrescription++;
                            continue;
                        }
                        rows.push(toRow(variant, taxon.category, dates));
                    }
                }

                await poster.post(rows, `${taxon.slug} page ${currentPage}/${pageCount}`);
                currentPage++;
                await sleep();
            } while (currentPage <= pageCount);
        }
    }

    poster.summary(`Site reports ${expectedTotal} across the roots (overlaps included); skipped ${skippedPrescription} prescription.`);
})();
