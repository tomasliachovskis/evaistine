import { buildRow, createPoster, currentWeek, fetchJson, MAX_PAGES, onlySelected, sleep, validEan } from './lib/pharmacy.js';

const BASE_URL = 'https://camelia.lt';
const STORE = 'Camelia';

// Nuxt front end over a Sylius API. The plain products endpoint is disabled,
// but the one the category pages use is open, 100 products a page, with the
// barcode, the prescription flag and the product's main category:
const SEARCH_URL = 'https://api.camelia.lt/api/v2/shop/search/products';

// Top-level categories by taxon id. Not scraped: 899 (prescription
// medicines), 902 "Pagal negalavimą" (by ailment, the same products again),
// gift vouchers, gifts, travel and promotion lists. The catch-all "Pradžia"
// taxon (892) can't be used instead: the search stops at 10 000 results.
const ROOTS = [
    { id: 898, key: 'nereceptiniai-vaistai', name: 'Nereceptiniai vaistai' },
    { id: 893, key: 'vitaminai-maisto-papildai-mineralai', name: 'Vitaminai, maisto papildai, mineralai' },
    { id: 894, key: 'kosmetika', name: 'Kosmetika' },
    { id: 895, key: 'higienos-prekes', name: 'Higienos prekės' },
    { id: 896, key: 'slaugos-prekes', name: 'Slaugos prekės' },
    { id: 897, key: 'medicinos-priemones-ir-iranga', name: 'Medicinos priemonės ir įranga' },
    { id: 900, key: 'arbatos-specializuotas-maistas', name: 'Arbatos, specializuotas maistas' },
    { id: 901, key: 'mamai-ir-vaikui', name: 'Mamai ir vaikui' },
    { id: 1831, key: 'sportui-ir-svorio-kontrolei', name: 'Sportui ir svorio kontrolei' },
];

// Main categories that aren't product types; a product filed under one of
// them gets the category it was listed under instead.
const NON_TYPE_TAXONS = ['pagal-negalavima', 'receptiniai-vaistai', 'akcijos', 'dovanos', 'kelionems'];

// "Root/Subcategory" from the product's main taxon, which is the full chain
// up to the "Pradžia" root (level 0). Level 1 is the top category.
const categoryOf = (product, listedUnder) => {
    const chain = [];
    for (let taxon = product.mainTaxon; taxon; taxon = taxon.parent) {
        chain.unshift(taxon);
    }
    const [, top, sub] = chain;
    const topSlug = top?.slug?.split('/').pop();
    if (!top || NON_TYPE_TAXONS.includes(topSlug)) {
        return listedUnder;
    }
    return sub ? `${top.name}/${sub.name}` : top.name;
};

// `price` is the current price, `originalPrices[0]` the price before a
// catalog promotion, and `loyaltyCatalogPromotionPrice` a lower price for
// loyalty-card holders. All in cents.
const toRow = (product, category, dates) => {
    const variant = product.variants?.find(v => v.code === product.defaultVariant?.code) ?? product.variants?.[0];
    const prices = variant?.prices ?? {};
    const current = prices.price ? prices.price / 100 : null;
    const before = prices.originalPrices?.[0] ? prices.originalPrices[0] / 100 : null;
    const loyalty = prices.loyaltyCatalogPromotionPrice ? prices.loyaltyCatalogPromotionPrice / 100 : null;
    const memberOnly = Boolean(loyalty && current && loyalty < current);
    const image = product.images?.[0]?.path;

    return buildRow({
        store: STORE,
        name: product.name,
        url: `${BASE_URL}/p/${product.slug}-${product.id}`,
        category,
        price: memberOnly ? loyalty : current,
        regular: memberOnly ? (before ?? current) : before,
        card: memberOnly,
        ean: validEan(variant?.barcode),
        image: image ? `https://images.camelia.lt/fit-in/600x600/filters:fill(white,1)${image}` : '',
        dates,
    });
};

(async () => {
    const dates = currentWeek();
    console.log(`Price validity week: ${dates.start_at} - ${dates.end_at}`);

    const poster = createPoster(STORE);
    const seen = new Set();
    let skippedPrescription = 0;
    let expectedTotal = 0;

    for (const root of onlySelected(ROOTS, r => r.key)) {
        let currentPage = 1;
        let pageCount = 1;

        do {
            const response = await fetchJson(SEARCH_URL, { 'productTaxons.id': root.id, page: currentPage, itemsPerPage: 100 });
            if (!Array.isArray(response?.data)) {
                console.log(`Giving up on ${root.key} page ${currentPage}.`);
                break;
            }
            if (currentPage === 1) {
                expectedTotal += response.meta?.totalItems ?? 0;
            }
            pageCount = Math.min(response.meta?.lastPage || 1, MAX_PAGES);

            const rows = [];
            for (const product of response.data) {
                if (seen.has(product.id)) {
                    continue;
                }
                seen.add(product.id);
                if (product.prescriptionMedicine) {
                    skippedPrescription++;
                    continue;
                }
                if (product.electronicGiftCard || product.physicalGiftCard) {
                    continue;
                }
                rows.push(toRow(product, categoryOf(product, root.name), dates));
            }

            await poster.post(rows, `${root.key} page ${currentPage}/${pageCount}`);
            currentPage++;
            await sleep();
        } while (currentPage <= pageCount);
    }

    poster.summary(`Site reports ${expectedTotal} across the roots (overlaps included); skipped ${skippedPrescription} prescription.`);
})();
