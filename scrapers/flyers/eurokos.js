import { launchBrowser, fetchBuffer, imagesToPdf, submitFlyer, extractCoverInfo } from './_shared.js';

// eurokos.lt/leidiniai/ is a Magento PageBuilder page whose CMS content
// (including the publication embeds) is only injected client-side via JS —
// a plain fetch returns none of it, so Puppeteer is needed just to render
// the page once for its final HTML. It embeds multiple
// Publitas flipbooks at once (same platform as Moki Veži — see
// moki-vezi.js): one monthly leaflet plus a couple of themed catalogs, each
// as `<div id="publitas-embed-{full view.publitas.com URL}">` preceded by
// an `<h3>` heading with its title. Only the monthly one's slug encodes a
// year-month ("eurokos_2026-09") — used as a coarse validity range (the
// leaflet's own pages carry no exact date text); the themed catalogs get
// no dates, same as elsewhere in this repo.
const LISTING_URL = 'https://www.eurokos.lt/leidiniai/';

const LAST_DAY_OF_MONTH = (year, month) => new Date(Date.UTC(year, month, 0)).getUTCDate();

const NAMED_ENTITIES = { amp: '&', lt: '<', gt: '>', quot: '"', apos: "'", nbsp: ' ', scaron: 'š', Scaron: 'Š', zcaron: 'ž', Zcaron: 'Ž', ccaron: 'č', Ccaron: 'Č' };

function decodeEntities(text) {
    return text.replace(/&#(\d+);|&(\w+);/g, (whole, numeric, named) => {
        if (numeric) return String.fromCodePoint(Number(numeric));
        return NAMED_ENTITIES[named] ?? whole;
    });
}

// The page's PageBuilder content is delivered as a JSON string embedded in
// an inline script (page.content() serializes it back out with its own
// JSON-escaping intact: `\"` for quotes, `\/` for slashes) rather than as
// plain rendered DOM — some publications get promoted into a real
// `id="publitas-embed-..."` div on load, others stay as this raw
// `<widget type="LeafletBlock" leaflet_link="...">` placeholder depending
// on load timing, so read every leaflet_link straight from the escaped
// JSON text instead of relying on which ones got rendered.
function findPublications(html) {
    return [...html.matchAll(/leaflet_link=\\"(https:\\\/\\\/view\.publitas\.com\\\/eurokos\\\/[a-zA-Z0-9_-]+)/g)]
        .map(m => m[1].replace(/\\\//g, '/'))
        .filter((url, i, arr) => arr.indexOf(url) === i)
        .map(url => {
            const escapedUrl = url.replace(/\//g, '\\/');
            const idx = html.indexOf(`leaflet_link=\\"${escapedUrl}`);
            const before = html.slice(Math.max(0, idx - 400), idx);
            const headingMatch = [...before.matchAll(/h3 id=\\"[^\\]*\\">([^<]+)</g)].pop();

            return { url, title: headingMatch ? decodeEntities(headingMatch[1]).trim() : 'Eurokos leidinys' };
        });
}

function monthRangeFromSlug(url) {
    const match = url.match(/(\d{4})-(\d{2})$/);
    if (!match) return { validFrom: null, validTo: null };

    const [, year, month] = match;
    return {
        validFrom: `${year}-${month}-01`,
        validTo: `${year}-${month}-${String(LAST_DAY_OF_MONTH(Number(year), Number(month))).padStart(2, '0')}`,
    };
}

(async () => {
    const browser = await launchBrowser();
    let html;

    try {
        const page = await browser.newPage();
        await page.goto(LISTING_URL, { waitUntil: 'networkidle2', timeout: 60000 });
        await new Promise(resolve => setTimeout(resolve, 3000));
        html = await page.content();
    } finally {
        await browser.close();
    }

    const publications = findPublications(html);
    console.log(`Found ${publications.length} publication(s) on ${LISTING_URL}`);

    for (const pub of publications) {
        try {
            const spreads = await (await fetch(`${pub.url}/spreads.json`)).json();
            const allPages = spreads.flatMap(spread => spread.pages);

            if (allPages.length === 0) {
                console.log(`No pages found for "${pub.title}"`);
                continue;
            }

            let { validFrom, validTo } = monthRangeFromSlug(pub.url);

            const buffers = [];
            for (const page of allPages) {
                buffers.push(await fetchBuffer(`https://view.publitas.com${page.images.at1600}`));
            }

            if (!validFrom) {
                const coverInfo = await extractCoverInfo({ store: 'Eurokos', imageBuffer: buffers[0], filename: 'eurokos-cover.jpg' });
                if (coverInfo?.validFrom) {
                    validFrom = coverInfo.validFrom;
                    validTo = coverInfo.validTo;
                } else {
                    console.log(`No year-month in slug or cover OCR for "${pub.title}" — submitting without dates`);
                }
            }

            const pdfBuffer = await imagesToPdf(buffers);

            await submitFlyer({
                store: 'Eurokos',
                title: pub.title,
                catalogName: 'Eurokos',
                validFrom,
                validTo,
                pdfBuffer,
                filename: `eurokos-${pub.url.split('/').pop()}.pdf`,
            });
        } catch (error) {
            console.error(`Failed to process "${pub.title}" (${pub.url}):`, error.message);
        }
    }
})();
