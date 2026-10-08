import { parse } from 'node-html-parser';
import { buildRow, createPoster, currentWeek, fetchText, loadEanCache, MAX_PAGES, onlySelected, parsePrice, sleep, validEan } from './lib/pharmacy.js';

const BASE_URL = 'https://www.gintarine.lt';
const STORE = 'Gintarinė vaistinė';

// nopCommerce. The catalog menu is in every page's header; a first-level
// category page (e.g. /burnos-higiena) lists the products of all its
// subcategories, server-rendered, 20 a page (?pagenumber=N, page size is
// fixed). Top-level pages (/higiena) only show product carousels, so those
// are not scraped themselves.
//
// Skipped roots: promotions (the same products again), gift vouchers, and
// prescription medicines.
const SKIPPED_ROOTS = ['akcijos-4', 'dovanu-kuponai', 'receptiniai-vaistai'];

// Most roots are by ailment ("Skausmas", "Sąnariai") and overlap with the
// product-type ones. Product-type roots go first, so a product listed in both
// keeps its product-type category; the rest follow in menu order.
const FIRST_ROOTS = ['kosmetika-6', 'higiena', 'medicinines-prekes-ir-iranga', 'akys-2', 'arbatos-ir-specializuotas-maistas', 'sportui-ir-laisvalaikiui', 'namams'];

const decode = (text) => parse(`<p>${text}</p>`).text.replace(/\s+/g, ' ').trim();

// Reads the first-level categories from the header menu. The menu markup
// leaves <li> unclosed, so it's tokenised rather than parsed as a tree; only
// the first copy of the menu (desktop) is read.
const readCategories = (html) => {
    const tokens = html.matchAll(/<ul class="navigation-catalog__list level-(\d)"|<\/ul>|<a class="navigation-catalog__link" href="\/([^"]+)">([\s\S]*?)<\/a>/g);
    const depth = [];
    const roots = [];
    let started = false;

    for (const token of tokens) {
        if (token[1] !== undefined) {
            depth.push(Number(token[1]));
            started = true;
        } else if (token[0] === '</ul>') {
            depth.pop();
            if (started && depth.length === 0) {
                break;
            }
        } else if (started) {
            const level = depth[depth.length - 1];
            const name = decode(token[3].replace(/<[^>]+>/g, ''));
            if (level === 0) {
                roots.push({ slug: token[2], name, children: [] });
            } else if (level === 1 && roots.length) {
                roots[roots.length - 1].children.push({ slug: token[2], name });
            }
        }
    }

    return roots;
};

const lastPage = (root) => {
    const last = root.querySelector('.paginator__last');
    return Number(last?.getAttribute('data-page')) || 1;
};

// One product card. The "Draugystė" member price (gv-club) is the promo
// price when there is one; product__price--regular is everyone's price.
// Ribbons hold the promo's wording ("-20%", "2 už 1 kainą").
const readCard = (form) => {
    const input = (name) => form.querySelector(`input[name="${name}"]`)?.getAttribute('value') ?? '';
    const link = form.querySelector('a.product__img-url')?.getAttribute('href') ?? '';
    const member = parsePrice(form.querySelector('.product__price--gv-club')?.text);
    const regular = parsePrice(form.querySelector('.product__price--regular')?.text) ?? parsePrice(input('productPrice'));
    const ribbons = form.querySelectorAll('.ribbon').map(r => r.text.trim()).filter(t => t && !/^-?\d+\s*%$/.test(t) && t !== 'Naujiena');

    return {
        id: input('productId') || form.getAttribute('data-productid'),
        name: decode(input('productName')),
        brand: decode(input('productBrand')),
        url: link ? `${BASE_URL}${link}` : '',
        image: form.querySelector('img.picture-img')?.getAttribute('src') ?? '',
        price: member ?? regular,
        regular,
        card: Boolean(member),
        condition: ribbons.join(', ') || null,
    };
};

// The barcode is only on the product page (`<meta itemprop="gtin">`).
// undefined = the page didn't load (see loadEanCache).
const fetchEan = async (url) => {
    const html = await fetchText(url);
    if (html === null) {
        return undefined;
    }
    const match = html.match(/itemprop="gtin" content="(\d+)"/);
    return match ? validEan(match[1]) : null;
};

(async () => {
    const dates = currentWeek();
    console.log(`Price validity week: ${dates.start_at} - ${dates.end_at}`);

    const home = await fetchText(BASE_URL);
    if (!home) {
        throw new Error('Could not load the homepage menu.');
    }

    const roots = readCategories(home).filter(root => !SKIPPED_ROOTS.includes(root.slug));
    roots.sort((a, b) => {
        const rank = (root) => (FIRST_ROOTS.includes(root.slug) ? FIRST_ROOTS.indexOf(root.slug) : FIRST_ROOTS.length);
        return rank(a) - rank(b);
    });
    console.log(`Categories: ${roots.map(r => `${r.name} (${r.children.length})`).join(', ')}`);

    const poster = createPoster(STORE);
    const eans = loadEanCache('gintarine-vaistine');
    const seen = new Set();

    for (const rootCategory of onlySelected(roots, r => r.slug)) {
        // A root without children (e.g. a single-page one) is listed itself.
        const categories = rootCategory.children.length ? rootCategory.children : [{ slug: rootCategory.slug, name: '' }];

        for (const category of categories) {
            const label = category.name ? `${rootCategory.name}/${category.name}` : rootCategory.name;
            let currentPage = 1;
            let pageCount = 1;

            do {
                const html = await fetchText(`${BASE_URL}/${category.slug}${currentPage > 1 ? `?pagenumber=${currentPage}` : ''}`);
                if (!html) {
                    console.log(`Giving up on ${category.slug} page ${currentPage}.`);
                    break;
                }
                const page = parse(html);
                pageCount = Math.min(lastPage(page), MAX_PAGES);

                // Only the category grid: pages also carry product carousels.
                const cards = page.querySelectorAll('.category-products__cards form[data-productid]')
                    .map(readCard)
                    .filter(card => card.id && card.url && !seen.has(card.id));
                cards.forEach(card => seen.add(card.id));

                await eans.fill(cards.map(card => card.url), fetchEan);

                const rows = cards.map(card => buildRow({ store: STORE, ...card, category: label, ean: eans.get(card.url), dates }));
                await poster.post(rows, `${category.slug} page ${currentPage}/${pageCount}`);

                currentPage++;
                await sleep();
            } while (currentPage <= pageCount);

            eans.save();
        }
    }

    eans.save();
    poster.summary(`${eans.fetched()} product pages fetched for EANs, ${eans.size()} in the cache.`);
})();
