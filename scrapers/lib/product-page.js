import { parse } from 'node-html-parser';
import { validEan } from './pharmacy.js';

// What a pharmacy's product page says about the product itself, for the
// product facts (docs/product-facts.md): the description text as plain text
// (composition, dose, usage are in there, in each pharmacy's own words) and
// the labelled attributes the page shows separately ("Gamintojas",
// "Veiklioji medžiaga", "Kilmės šalis", ...). Turning the text into facts is
// the backend's job (product-facts:extract), not the scraper's.
//
// Each extractor gets the page HTML and returns { ean, text, attributes,
// name, category }.

const MAX_TEXT = 12000;

// HTML fragment -> plain text with one line per block element.
export const htmlToText = (html) => {
    if (!html) {
        return '';
    }
    const text = parse(String(html)
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<li[^>]*>/gi, '\n- ')
        .replace(/<\/(p|div|li|tr|h[1-6]|ul|ol|table)>/gi, '\n')
        .replace(/<(td|th)[^>]*>/gi, ' | ')).text;

    return compactLeaflet(text
        .replace(/<\?xml[^>]*>/g, '')
        .replace(/\u00a0/g, ' ')
        .split('\n')
        .map(line => line.replace(/[ \t]+/g, ' ').trim())
        .filter(Boolean)
        .join('\n')
        .replace(/^-\n/gm, '- '))
        .slice(0, MAX_TEXT);
};

// A medicine's page carries the whole package leaflet (20 000+ characters).
// The facts we use from it are the opening (name, strength, form) and section
// 6, "Pakuotės turinys ir kita informacija" (active substance and amount,
// excipients, pack contents), so only those two parts are kept.
const LEAFLET_SECTION_6 = 'Pakuotės turinys ir kita informacija';
const compactLeaflet = (text) => {
    const at = text.lastIndexOf(LEAFLET_SECTION_6);
    if (text.length <= 6000 || at < 0 || !/lapel/i.test(text.slice(0, 1500))) {
        return text;
    }
    return `${text.slice(0, 1200)}\n...\n${text.slice(at, at + 4500)}`;
};

const clean = (value) => String(value ?? '').replace(/\s+/g, ' ').replace(/:\s*$/, '').trim();

const addAttribute = (attributes, label, value) => {
    const key = clean(label);
    const val = clean(value);
    if (key && val && key.length <= 60 && !(key in attributes)) {
        attributes[key] = val.slice(0, 500);
    }
};

// Labels we never keep: shop internals, not facts about the product.
const SKIP_LABELS = /^(prekės kodas|galioja iki|kodas|sku)$/i;

// The page's product name (first <h1>) and its category path from the
// breadcrumb (without the home link and the product itself): context for the
// extraction, which runs locally where new products aren't in the DB yet.
const pageContext = (html) => {
    const root = parse(html);
    const name = clean(root.querySelector('h1')?.text) || null;

    let crumbs = root.querySelectorAll('.breadcrumbs__item, .breadcrumb-item, nav.breadcrumb li')
        .map(el => clean(el.text))
        .filter(Boolean);
    if (crumbs.length === 0) {
        for (const match of html.matchAll(/<script type="application\/ld\+json"[^>]*>([\s\S]*?)<\/script>/g)) {
            if (!match[1].includes('BreadcrumbList')) {
                continue;
            }
            try {
                const data = JSON.parse(match[1]);
                const list = data['@type'] === 'BreadcrumbList' ? data : (data['@graph'] ?? []).find(item => item['@type'] === 'BreadcrumbList');
                crumbs = (list?.itemListElement ?? []).map(item => clean(item.name ?? item.item?.name)).filter(Boolean);
            } catch (error) {
                // Not JSON we can read: no category.
            }
            break;
        }
    }
    if (crumbs.length && name && crumbs[crumbs.length - 1].toLowerCase() === name.toLowerCase()) {
        crumbs.pop();
    }
    const category = crumbs.slice(1).join(' / ') || null;

    return { name: name ? name.slice(0, 500) : null, category: category ? category.slice(0, 500) : null };
};

const finish = ({ ean, text, attributes }, html) => {
    for (const key of Object.keys(attributes)) {
        if (SKIP_LABELS.test(key)) {
            delete attributes[key];
        }
    }
    return { ean: ean ?? null, text: text ?? '', attributes, ...pageContext(html) };
};

// Gintarinė: the description is the accordion item
// `li.single-product__details-description`, the attributes are
// "<strong>Gamintojas:</strong> ..." lines in the "Daugiau informacijos" item,
// and "Kilmės šalis:" sits next to the price.
const gintarine = (html) => {
    const root = parse(html);
    const gtin = html.match(/itemprop="gtin" content="(\d+)"/);
    const attributes = {};

    const more = html.indexOf('Daugiau informacijos');
    if (more >= 0) {
        const block = html.slice(more, more + 3000);
        for (const match of block.matchAll(/<strong>([^<]{2,40}):<\/strong>\s*(?:<a[^>]*>)?([^<]{1,200})/g)) {
            addAttribute(attributes, match[1], match[2]);
        }
    }
    const origin = html.match(/Kilmės šalis:\s*(?:<[^>]+>\s*)*([^<]{2,60})</);
    if (origin) {
        addAttribute(attributes, 'Kilmės šalis', origin[1]);
    }

    // Medicines carry the whole leaflet, whose markup the parser can't nest
    // (the container comes back empty); fall back to the raw slice.
    // The accordion items: "Aprašymas", "Sudėtis", "Naudojimo ir laikymo
    // sąlygos", "Įspėjimai" (cosmetics) or one "Informacinis lapelis"
    // (supplements, medicines). The stock list and "Daugiau informacijos"
    // (read above) are not text about the product.
    const parts = [];
    root.querySelectorAll('ul.product-accordion > li').forEach(li => {
        const title = clean(li.querySelector('.accordion__toggle')?.text);
        if (/stock/.test(li.classList.value.join(' ')) || /^Daugiau informacijos/i.test(title)) {
            return;
        }
        const body = htmlToText(li.querySelector('.accordion__content')?.innerHTML ?? '');
        if (body.replace(/[-\s]/g, '') !== '') {
            parts.push(title ? `${title}\n${body}` : body);
        }
    });
    let text = parts.join('\n');

    // Medicines carry the whole leaflet, whose markup the parser can't nest
    // (the accordion comes back empty); fall back to the raw slice.
    if (text.length < 40) {
        const start = html.indexOf('single-product__details-description');
        const end = html.indexOf('single-product__info', start);
        text = start >= 0 ? htmlToText(html.slice(start, end > start ? end : start + 60000).replace(/^[^>]*>/, '')) : '';
    }

    return finish({ ean: gtin ? validEan(gtin[1]) : null, text, attributes }, html);
};

// Apotheka: both live in `<script type="application/json" data-ssr-config>`
// blocks: the description tab's `fullText` and productMetaDetails' `items`
// (Veiklioji medžiaga, Stiprumas, Produkto/Pakuotės forma, Kiekis pakuotėje,
// Kilmės šalis).
const apotheka = (html) => {
    const gtin = html.match(/"gtin1?3?":"(\d+)"/);
    const attributes = {};
    let text = '';

    for (const match of html.matchAll(/<script type="application\/json" data-ssr-config>([\s\S]*?)<\/script>/g)) {
        let config;
        try {
            config = JSON.parse(match[1]);
        } catch (error) {
            continue;
        }
        for (const tab of config.tabs ?? []) {
            if (tab.type === 'description' && tab.descriptionConfig?.fullText && !text) {
                text = htmlToText(tab.descriptionConfig.fullText);
            }
        }
        if (Array.isArray(config.items)) {
            config.items.forEach(item => addAttribute(attributes, item.label, item.value));
        }
    }

    const brand = html.match(/"brand":\{"@type":"Brand","name":"([^"]+)"/);
    if (brand) {
        addAttribute(attributes, 'Prekės ženklas', brand[1]);
    }

    return finish({ ean: gtin ? validEan(gtin[1]) : null, text, attributes }, html);
};

// Mano vaistinė: description in `div#info`, attributes in
// `.product-attribute-line` (name/value spans).
const manoVaistine = (html) => {
    const root = parse(html);
    const gtin = html.match(/itemprop="gtin13" content="(\d+)"/) ?? html.match(/"gtin13":"(\d+)"/);
    const attributes = {};

    root.querySelectorAll('.product-attribute-line').forEach(line => {
        addAttribute(attributes, line.querySelector('.product-attribute-name')?.text, line.querySelector('.product-attribute-value')?.text);
    });

    return finish({
        ean: gtin ? validEan(gtin[1]) : null,
        text: htmlToText(root.querySelector('#info')?.innerHTML ?? ''),
        attributes,
    }, html);
};

export const PRODUCT_PAGE_EXTRACTORS = {
    'gintarine-vaistine': { store: 'Gintarinė vaistinė', extract: gintarine },
    apotheka: { store: 'Apotheka', extract: apotheka },
    'mano-vaistine': { store: 'Mano vaistinė', extract: manoVaistine },
};
