import { launchBrowser, fetchBuffer, imagesToPdf, isValidJpeg, submitFlyer, sleep, extractCoverInfo, renderFirstPdfPageToJpeg } from './_shared.js';

// Iki's /leidiniai/ listing is plain server-rendered HTML with two distinct
// sections: a "hero" block above the grid holding the current main weekly
// leaflet — with a real, directly downloadable PDF — plus a grid of smaller
// per-store/themed leaflets below it, each linking to a detail page that
// embeds an Issuu flipbook (no plain PDF for those).
const LISTING_URL = 'https://iki.lt/leidiniai/';

function parseDateRange(text) {
    if (!text) return { validFrom: null, validTo: null };

    const [fromRaw, toRaw] = text.split('-').map(s => s.trim());
    if (!fromRaw || !toRaw) return { validFrom: null, validTo: null };

    const toIso = s => s.trim().split(/\s+/).join('-');
    return { validFrom: toIso(fromRaw), validTo: toIso(toRaw) };
}

async function findHeroLeaflet(page) {
    return page.$eval('.single-page-container', container => {
        const title = container.parentElement.querySelector('.title')?.textContent.replace(/\s+/g, ' ').trim() ?? '';
        const dateText = container.querySelector('.date-block')?.textContent.replace(/\s+/g, ' ').trim() ?? '';
        const dateMatch = dateText.match(/(\d{4}\s+\d{2}\s+\d{2})\s*-\s*(\d{4}\s+\d{2}\s+\d{2})/);
        const href = container.querySelector('a[href$=".pdf"]')?.getAttribute('href') ?? null;

        return { title, href, dateText: dateMatch ? dateMatch[0] : '' };
    }).catch(() => null);
}

async function findLeaflets(page) {
    return page.$$eval('.publication-list-item-container', cards => cards.map(card => {
        const link = card.querySelector('a[href]');
        const dateText = card.querySelector('p')?.textContent.replace(/\s+/g, ' ').trim() ?? '';
        const dateMatch = dateText.match(/(\d{4}\s+\d{2}\s+\d{2})\s*-\s*(\d{4}\s+\d{2}\s+\d{2})/);
        const href = link?.getAttribute('href') ?? null;

        // Several of these small per-store leaflets share the exact same
        // generic title and validity dates (e.g. "specialus-pasiulymai-iki-
        // parduotuves-klientams-181/" vs "-182/" both render as "Specialūs
        // pasiūlymai IKI parduotuvės klientams") even though they're
        // genuinely different leaflets — the backend dedupes on
        // (store, dates, title), so two identical titles silently collapse
        // into one. Append the URL's own trailing id to keep titles unique
        // whenever the site itself gives no other distinguishing text.
        const idMatch = href?.match(/-(\d+)\/?$/);
        let title = link?.textContent.replace(/\s+/g, ' ').trim() ?? '';
        if (idMatch && !title.includes(idMatch[1])) title = `${title} (${idMatch[1]})`;

        return { title, href, dateText: dateMatch ? dateMatch[0] : '' };
    }));
}

// Each detail page embeds `<iframe src="https://e.issuu.com/embed.html?d={docname}&u={username}">`.
// Loading that embed URL triggers requests to
// `https://svg.issuu.com/{revisionId}-{publicationId}/page_{n}.svg` — the
// same `{revisionId}-{publicationId}` slug also serves real JPEGs at
// `https://image.isu.pub/{slug}/jpg/page_{n}.jpg` with no auth/referer
// needed, so sniff that slug from the first such request and fetch pages
// directly rather than driving the Issuu reader UI.
async function fetchLeafletPdf(browser, detailHref) {
    const detailPage = await browser.newPage();

    try {
        await detailPage.goto(detailHref, { waitUntil: 'networkidle2', timeout: 60000 });

        const iframeSrc = await detailPage.$eval('iframe[src*="issuu.com/embed.html"]', el => el.getAttribute('src'));
        if (!iframeSrc) throw new Error('No Issuu embed iframe found on detail page');

        const embedPage = await browser.newPage();
        let slug = null;

        embedPage.on('response', response => {
            if (slug) return;
            const match = response.url().match(/svg\.issuu\.com\/([^/]+)\/page_\d+\.svg/);
            if (match) slug = match[1];
        });

        try {
            await embedPage.goto(iframeSrc, { waitUntil: 'networkidle2', timeout: 60000 });
            for (let waited = 0; waited < 15 && !slug; waited++) await sleep(1000);
        } finally {
            await embedPage.close();
        }

        if (!slug) throw new Error('Could not determine Issuu page-image slug');

        const buffers = [];
        for (let n = 1; ; n++) {
            const pageUrl = `https://image.isu.pub/${slug}/jpg/page_${n}.jpg`;
            let buffer;

            try {
                buffer = await fetchBuffer(pageUrl);
            } catch (error) {
                break;
            }

            // Issuu's CDN answers an out-of-range page with 200 and a
            // non-JPEG body instead of a real 404 — confirmed live: this
            // used to crash the whole run (pdf-lib's JpegEmbedder throwing
            // "SOI not found in JPEG") instead of the loop just stopping
            // here like it does on a real 404. One retry first, in case
            // it's a genuine transient blip on a real page rather than the
            // end of the document.
            if (!isValidJpeg(buffer)) {
                await sleep(1000);
                buffer = await fetchBuffer(pageUrl).catch(() => null);

                if (!buffer || !isValidJpeg(buffer)) break;
            }

            buffers.push(buffer);
        }

        if (buffers.length === 0) throw new Error('No page images fetched');

        return { buffers, pdfBuffer: await imagesToPdf(buffers) };
    } finally {
        await detailPage.close();
    }
}

(async () => {
    const browser = await launchBrowser();

    try {
        const page = await browser.newPage();
        await page.goto(LISTING_URL, { waitUntil: 'domcontentloaded' });

        const hero = await findHeroLeaflet(page);
        const leaflets = await findLeaflets(page);
        console.log(`Found hero leaflet: ${hero ? hero.title : 'none'}; ${leaflets.length} grid leaflet(s) on ${LISTING_URL}`);

        if (hero?.href && hero.title) {
            let { validFrom, validTo } = parseDateRange(hero.dateText);

            const issueMatch = hero.title.match(/Nr\.\s*(\d+)/i);

            try {
                const pdfBuffer = await fetchBuffer(hero.href);

                if (!validFrom) {
                    const coverImage = await renderFirstPdfPageToJpeg(hero.href);
                    const coverInfo = await extractCoverInfo({ store: 'Iki', imageBuffer: coverImage, filename: 'iki-hero-cover.jpg' });
                    if (coverInfo?.validFrom) {
                        validFrom = coverInfo.validFrom;
                        validTo = coverInfo.validTo;
                    } else {
                        console.log(`No date range for hero leaflet "${hero.title}" (site text or cover OCR) — submitting without dates`);
                    }
                }

                await submitFlyer({
                    store: 'Iki',
                    title: hero.title,
                    catalogName: 'Iki',
                    issueNumber: issueMatch ? issueMatch[1] : null,
                    validFrom,
                    validTo,
                    pdfBuffer,
                    sourcePdfUrl: hero.href,
                    filename: `iki-${hero.href.split('/').pop()}`,
                    sourceId: hero.href,
                });
            } catch (error) {
                console.error(`Failed to process hero leaflet "${hero.title}":`, error.message);
            }
        }

        for (const leaflet of leaflets) {
            if (!leaflet.href || !leaflet.title) {
                console.log('Skipping leaflet with missing data:', leaflet);
                continue;
            }

            let { validFrom, validTo } = parseDateRange(leaflet.dateText);

            try {
                const { buffers, pdfBuffer } = await fetchLeafletPdf(browser, leaflet.href);

                if (!validFrom) {
                    const coverInfo = await extractCoverInfo({ store: 'Iki', imageBuffer: buffers[0], filename: 'iki-cover.jpg' });
                    if (coverInfo?.validFrom) {
                        validFrom = coverInfo.validFrom;
                        validTo = coverInfo.validTo;
                    } else {
                        console.log(`No date range for "${leaflet.title}" (site text or cover OCR) — submitting without dates`);
                    }
                }

                await submitFlyer({
                    store: 'Iki',
                    title: leaflet.title,
                    catalogName: 'Iki',
                    validFrom,
                    validTo,
                    pdfBuffer,
                    filename: `iki-${leaflet.href.replace(/\W+/g, '-').replace(/^-+|-+$/g, '')}.pdf`,
                    sourceId: leaflet.href,
                });
            } catch (error) {
                console.error(`Failed to process leaflet "${leaflet.title}":`, error.message);
            }
        }
    } finally {
        await browser.close();
    }
})();
