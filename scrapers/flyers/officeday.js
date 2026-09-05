import { launchBrowser, fetchBuffer, imagesToPdf, submitFlyer, extractCoverInfo } from './_shared.js';

// officeday.lt/lt/produktu-katalogai is a Next.js SPA (its "Produktų
// katalogai" content is only injected client-side, a plain fetch returns
// none of it) listing several catalog cards at once — a current monthly
// leaflet plus a handful of themed/seasonal catalogs (Christmas gifts,
// business gifts, school supplies, work calendars, etc), each linking out to
// either an Issuu-hosted flipbook (issuu.com/docs/{id}) or a Publitas one
// (view.publitas.com/{account}/{slug}/ — same platform as Eurokos/Moki Veži,
// see eurokos.js/moki-vezi.js). Issuu's document page is plain server-
// rendered HTML with a JSON-escaped blob carrying pageCount, and its
// og:image meta tag already gives the exact `{revisionId}-{publicationId}`
// pair needed to build every other page's plain public JPEG URL
// (image.isu.pub/{revisionId}-{publicationId}/jpg/page_{n}.jpg) — no embed
// API needed. None of the catalogs carry validity dates in their own
// metadata or page text, so every cover page goes through the backend's
// cover-OCR endpoint, same as Camelia/Aibė.
const LISTING_URL = 'https://officeday.lt/lt/produktu-katalogai';

async function findCatalogs(page) {
    return page.$$eval('a', as => as
        .map(a => ({
            href: a.href,
            title: a.closest('div')?.parentElement?.querySelector('p')?.textContent.trim() ?? null,
        }))
        .filter(c => c.title && (c.href.includes('issuu.com/docs/') || c.href.includes('view.publitas.com/'))));
}

async function fetchIssuuPages(url) {
    // Issuu serves a completely different (JS-only, no embedded JSON) reader
    // page when the URL carries its own tracking query string (some cards
    // link out with a `?fr=...` referral param) — strip it so this always
    // hits the plain document page.
    const cleanUrl = url.split('?')[0];
    const html = await (await fetch(cleanUrl)).text();

    const baseMatch = html.match(/image\.isu\.pub\/(\d+-[a-f0-9]+)\/jpg/);
    const pageCountMatch = html.match(/\\"pageCount\\":(\d+)/);
    if (!baseMatch || !pageCountMatch) return null;

    const baseUrl = `https://image.isu.pub/${baseMatch[1]}/jpg`;
    const pageCount = Number(pageCountMatch[1]);

    const buffers = [];
    for (let n = 1; n <= pageCount; n++) {
        buffers.push(await fetchBuffer(`${baseUrl}/page_${n}.jpg`));
    }
    return buffers;
}

async function fetchPublitasPages(url) {
    const match = url.match(/view\.publitas\.com\/([a-zA-Z0-9_-]+\/[a-zA-Z0-9_-]+)/);
    if (!match) return null;

    const spreads = await (await fetch(`https://view.publitas.com/${match[1]}/spreads.json`)).json();
    const allPages = spreads.flatMap(spread => spread.pages);

    const buffers = [];
    for (const p of allPages) {
        buffers.push(await fetchBuffer(`https://view.publitas.com${p.images.at1600}`));
    }
    return buffers;
}

(async () => {
    const browser = await launchBrowser();
    let catalogs;

    try {
        const page = await browser.newPage();
        await page.goto(LISTING_URL, { waitUntil: 'networkidle2', timeout: 60000 });
        await new Promise(resolve => setTimeout(resolve, 3000));
        catalogs = await findCatalogs(page);
    } finally {
        await browser.close();
    }

    console.log(`Found ${catalogs.length} catalog(s) on ${LISTING_URL}`);

    for (const catalog of catalogs) {
        try {
            const buffers = catalog.href.includes('issuu.com/docs/')
                ? await fetchIssuuPages(catalog.href)
                : await fetchPublitasPages(catalog.href);

            if (!buffers || buffers.length === 0) {
                console.log(`No pages found for "${catalog.title}" (${catalog.href})`);
                continue;
            }

            const coverInfo = await extractCoverInfo({ store: 'Officeday', imageBuffer: buffers[0], filename: 'officeday-cover.jpg' });
            if (!coverInfo?.validFrom) {
                console.log(`No reliable dates from cover OCR for "${catalog.title}" — submitting without dates`);
            }

            const pdfBuffer = await imagesToPdf(buffers);

            await submitFlyer({
                store: 'Officeday',
                title: catalog.title,
                catalogName: 'Officeday',
                validFrom: coverInfo?.validFrom ?? null,
                validTo: coverInfo?.validTo ?? null,
                pdfBuffer,
                filename: `officeday-${catalog.href.match(/docs\/([a-zA-Z0-9]+)/)?.[1] || catalog.href.split('/').filter(Boolean).pop()}.pdf`,
            });
        } catch (error) {
            console.error(`Failed to process catalog "${catalog.title}" (${catalog.href}):`, error.message);
        }
    }
})();
