import { launchBrowser, fetchBuffer, imagesToPdf, submitFlyer, sleep } from './_shared.js';

const LISTING_URL = 'https://www.lidl.lt/c/kainu-leidiniai/s10020254';

function addDays(isoDate, days) {
    const date = new Date(`${isoDate}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);
    return date.toISOString().slice(0, 10);
}

function decodeImgproxyPath(src) {
    try {
        const { pathname } = new URL(src);
        const lastSegment = pathname.split('/').pop();
        const base64 = lastSegment.replace(/\.jpg$/, '');
        return Buffer.from(base64, 'base64url').toString('utf8');
    } catch (error) {
        return null;
    }
}

async function dismissCookieDialog(page) {
    try {
        const button = await page.$('#onetrust-accept-btn-handler');
        if (button) {
            await button.click();
            await sleep(500);
        }
    } catch (error) {
        // no dialog present — nothing to do
    }
}

async function findLeaflets(page) {
    return page.evaluate(() => Array.from(document.querySelectorAll('a.flyer[href]')).map(a => ({
        href: a.getAttribute('href'),
        text: a.textContent.replace(/\s+/g, ' ').trim(),
    })));
}

async function getTotalPages(detailPage) {
    const text = await detailPage.evaluate(() => document.body.innerText);
    const match = text.match(/(\d+)\s*\/\s*(\d+)/);
    return match ? parseInt(match[2], 10) : 1;
}

async function goToPage(detailPage, slug, n) {
    const url = `https://www.lidl.lt/l/lt/leidinys/${slug}/view/flyer/page/${n}`;

    for (let attempt = 0; attempt < 3; attempt++) {
        try {
            await detailPage.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
            return;
        } catch (error) {
            console.log(`Navigation retry for ${slug} page ${n} (${error.message})`);
            await sleep(1500);
        }
    }

    throw new Error(`Could not navigate to page ${n} after retries`);
}

// Rather than requiring page N's own image to exist after navigating to page
// N — which broke at the boundary: the viewer's own "N / total" counter
// counts one more "page" than there are distinct page-NN images (the very
// last one appears to be an empty back-cover slot with no image of its
// own) — passively collect every distinct page-NN image encountered via
// network responses while sweeping through every page URL, and use
// whatever was actually found. This also sidesteps the virtualized-list
// timing issues a DOM read right after navigation was prone to.
async function fetchLeafletPdf(browser, slug) {
    const detailPage = await browser.newPage();
    detailPage.setDefaultNavigationTimeout(45000);

    const pageImages = new Map();

    detailPage.on('response', response => {
        const url = response.url();
        if (!url.includes('imgproxy.leaflets.schwarz')) return;

        const decoded = decodeImgproxyPath(url);
        const match = decoded && decoded.match(/page-(\d+)_/);
        if (!match) return;

        const n = parseInt(match[1], 10);
        if (!pageImages.has(n)) pageImages.set(n, url);
    });

    try {
        await goToPage(detailPage, slug, 1);
        await dismissCookieDialog(detailPage);
        await sleep(1500);

        const totalPages = await getTotalPages(detailPage);
        console.log(`Leaflet ${slug}: ${totalPages} page(s) reported by viewer`);

        for (let n = 1; n <= totalPages; n++) {
            await goToPage(detailPage, slug, n);
            await sleep(1200);
        }

        const collected = Array.from(pageImages.keys()).sort((a, b) => a - b);
        console.log(`Leaflet ${slug}: collected ${collected.length}/${totalPages} distinct page image(s)`);

        if (collected.length < totalPages - 1) {
            throw new Error(`Only found ${collected.length}/${totalPages} page images — too many missing to trust`);
        }

        const buffers = [];
        for (const n of collected) {
            buffers.push(await fetchBuffer(pageImages.get(n)));
        }

        return imagesToPdf(buffers);
    } finally {
        await detailPage.close();
    }
}

(async () => {
    const browser = await launchBrowser();

    try {
        const page = await browser.newPage();
        await page.goto(LISTING_URL, { waitUntil: 'domcontentloaded' });
        await dismissCookieDialog(page);

        const leaflets = await findLeaflets(page);
        console.log(`Found ${leaflets.length} link(s) on ${LISTING_URL}`);

        for (const leaflet of leaflets) {
            const slugMatch = leaflet.href.match(/\/leidinys\/([^/]+)\//);
            if (!slugMatch) continue;
            const slug = slugMatch[1];

            // Only the two weekly leaflets carry an exact date in their slug
            // ("...-20260824-kw35-pr") — a genuine Mon-Sun week, so +6 days
            // is a safe valid_to. Everything else on this page (the alcohol
            // "Skonio atradimai" leaflet, and "Katalogai" like grill/back-
            // to-school/loyalty promos) only shows a "Nuo {month} {day} d."
            // teaser with no real end date anywhere — not on the listing,
            // and confirmed (via Gemini OCR on the cover page) not printed
            // on the leaflet itself either. Rather than guess a wrong end
            // date, submit these with no dates at all — the backend treats
            // them as always-current with no expiry, and the existing
            // ordered() scope (valid_from DESC) already sorts undated
            // leaflets below dated ones.
            const slugDateMatch = slug.match(/(\d{4})(\d{2})(\d{2})-kw\d+/);
            let validFrom = null;
            let validTo = null;

            if (slugDateMatch) {
                validFrom = `${slugDateMatch[1]}-${slugDateMatch[2]}-${slugDateMatch[3]}`;
                validTo = addDays(validFrom, 6);
            } else {
                console.log(`No reliable end date for "${leaflet.text}" — submitting without dates`);
            }

            const title = leaflet.text.replace(/Nuo\s+\S+\s+\d{1,2}\s*d\.?$/i, '').trim();
            const weekMatch = slug.match(/kw(\d+)/);

            try {
                const pdfBuffer = await fetchLeafletPdf(browser, slug);

                await submitFlyer({
                    store: 'Lidl',
                    title,
                    catalogName: 'Lidl',
                    issueNumber: weekMatch ? weekMatch[1] : null,
                    validFrom,
                    validTo,
                    pdfBuffer,
                    filename: `lidl-${slug}.pdf`,
                });
            } catch (error) {
                console.error(`Failed to process leaflet "${title}" (${slug}):`, error.message);
            }
        }
    } finally {
        await browser.close();
    }
})();
