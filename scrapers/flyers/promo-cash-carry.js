import { launchBrowser, fetchBuffer, imagesToPdf, submitFlyer, extractCoverInfo } from './_shared.js';

// epromo.lt/leidiniai is a Cloudflare-protected client-rendered SPA (a
// plain fetch/curl gets a JS challenge page) — Puppeteer is needed just to
// render the listing once. It lists leaflets for both "PROMO Cash&Carry"
// (this store) and a separate "PROMO HORECA"/Food Service wholesale brand
// (submitted under the separate "ePromo" store by epromo.js instead) —
// only cards whose title mentions Cash&Carry are kept here. Each leaflet is an embedded
// Issuu flipbook (`issuu.com/docs/{hash}`); Issuu's own doc page (fetched
// with a browser User-Agent — a bare curl UA gets blocked) embeds a JSON
// blob with `revisionId`/`publicationId`/`pageCount`, which combine into
// page-image URLs (`image.isu.pub/{revisionId}-{publicationId}/jpg/
// page_{n}.jpg`, no auth needed) — no PDF download exists. The listing
// page carries no validity-date text at all, so dates are read from the
// leaflet's own cover via the backend's Gemini cover-OCR endpoint.
const LISTING_URL = 'https://epromo.lt/leidiniai';
const ISSUU_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

function decodeAmp(text) {
    return text.replace(/&amp;/g, '&');
}

async function findLeaflets(page) {
    const html = await page.content();

    return html.split('<li class="h-[200px]').slice(1).map(block => {
        const docMatch = block.match(/issuu\.com\/docs\/([a-f0-9]+)/);
        const altMatch = block.match(/alt="([^"]*)"/);

        if (!docMatch) return null;
        return { docId: docMatch[1], title: altMatch ? decodeAmp(altMatch[1]).trim() : 'ePromo leidinys' };
    }).filter(Boolean);
}

async function fetchIssuuMeta(docId) {
    const html = await (await fetch(`https://issuu.com/docs/${docId}`, {
        headers: { 'User-Agent': ISSUU_USER_AGENT },
    })).text();

    const pageCountMatch = html.match(/"pageCount\\":(\d+)/);
    const revisionMatch = html.match(/"revisionId\\":\\"(\d+)\\"/);

    if (!pageCountMatch || !revisionMatch) return null;

    return { pageCount: Number(pageCountMatch[1]), revisionId: revisionMatch[1] };
}

// Issuu serves most `.../jpg/page_{n}.jpg` URLs as real JPEGs, but at least
// one cover page came back as a WebP despite the ".jpg" URL — pdf-lib can't
// embed WebP, so any such buffer is re-rendered to JPEG the same way
// aibe.js converts FlipHTML5's WebP pages: navigate a page directly to the
// image URL (Chromium decodes WebP natively) and screenshot it at its
// natural size.
async function ensureJpeg(browser, buffer, url) {
    const isWebp = buffer[8] === 0x57 && buffer[9] === 0x45 && buffer[10] === 0x42 && buffer[11] === 0x50;
    if (!isWebp) return buffer;

    const page = await browser.newPage();
    try {
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });
        const { width, height } = await page.evaluate(() => {
            const img = document.querySelector('img');
            return { width: img.naturalWidth, height: img.naturalHeight };
        });
        await page.setViewport({ width, height });
        return await page.screenshot({ type: 'jpeg', quality: 85 });
    } finally {
        await page.close();
    }
}

(async () => {
    const browser = await launchBrowser();
    let leaflets;

    try {
        const page = await browser.newPage();
        await page.goto(LISTING_URL, { waitUntil: 'networkidle2', timeout: 60000 });
        await new Promise(resolve => setTimeout(resolve, 3000));
        leaflets = await findLeaflets(page);

        console.log(`Found ${leaflets.length} leaflet(s) on ${LISTING_URL}`);

        for (const leaflet of leaflets) {
            if (!/cash\s*&?\s*carry/i.test(leaflet.title)) {
                console.log(`Skipping "${leaflet.title}" — not a PROMO Cash&Carry leaflet`);
                continue;
            }

            try {
                const meta = await fetchIssuuMeta(leaflet.docId);
                if (!meta) {
                    console.log(`No Issuu metadata found for "${leaflet.title}" (${leaflet.docId})`);
                    continue;
                }

                console.log(`Found leaflet "${leaflet.title}" with ${meta.pageCount} page(s)`);

                const buffers = [];
                for (let n = 1; n <= meta.pageCount; n++) {
                    const url = `https://image.isu.pub/${meta.revisionId}-${leaflet.docId}/jpg/page_${n}.jpg`;
                    buffers.push(await ensureJpeg(browser, await fetchBuffer(url), url));
                }

                const coverInfo = await extractCoverInfo({ store: 'Promo Cash&Carry', imageBuffer: buffers[0], filename: 'promo-cc-cover.jpg' });
                if (!coverInfo?.validFrom) {
                    console.log(`No reliable dates from cover OCR for "${leaflet.title}" — submitting without dates`);
                }

                const pdfBuffer = await imagesToPdf(buffers);

                await submitFlyer({
                    store: 'Promo Cash&Carry',
                    title: coverInfo?.title || leaflet.title,
                    catalogName: 'Promo Cash&Carry',
                    validFrom: coverInfo?.validFrom ?? null,
                    validTo: coverInfo?.validTo ?? null,
                    pdfBuffer,
                    filename: `promo-cash-carry-${leaflet.docId}.pdf`,
                    sourceId: leaflet.docId,
                });
            } catch (error) {
                console.error(`Failed to process leaflet "${leaflet.title}" (${leaflet.docId}):`, error.message);
            }
        }
    } finally {
        await browser.close();
    }
})();
