import { launchBrowser, fetchBuffer, submitFlyer, sleep } from './_shared.js';

const LISTING_URL = 'https://www.lidl.lt/c/kainu-leidiniai/s10020254';

function addDays(isoDate, days) {
    const date = new Date(`${isoDate}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);
    return date.toISOString().slice(0, 10);
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

async function goToUrl(detailPage, url, description) {
    for (let attempt = 0; attempt < 3; attempt++) {
        try {
            await detailPage.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });
            return;
        } catch (error) {
            console.log(`Navigation retry for ${description} (${error.message})`);
            await sleep(1500);
        }
    }

    throw new Error(`Could not navigate to ${description} after retries`);
}

// Used to assemble the PDF page-by-page from screenshotted viewer images —
// fragile (a network-response race against the virtualized page list
// regularly dropped a handful of pages out of e.g. 43, confirmed live
// 2026-09-18: 39/43 collected, leaflet rejected as untrustworthy). The
// viewer's own "Navigacija" menu has a real "Atsisiųsti PDF" link straight
// to the original PDF on Schwarz's asset CDN — use that directly instead of
// reconstructing the file ourselves. The menu panel is only mounted once its
// own route is loaded (confirmed live: present on /view/menu/page/1, absent
// from a fresh load of /view/flyer/page/1 even after waiting — it's not
// just a hidden-until-clicked overlay), so navigate straight to that route
// rather than the flyer-viewer one.
async function fetchLeafletPdf(browser, slug) {
    const detailPage = await browser.newPage();
    detailPage.setDefaultNavigationTimeout(45000);

    try {
        await goToUrl(detailPage, `https://www.lidl.lt/l/lt/leidinys/${slug}/view/menu/page/1`, `${slug} menu`);
        await dismissCookieDialog(detailPage);

        let pdfUrl = null;
        try {
            await detailPage.waitForSelector('a[href*="assets.leaflets.schwarz"][href$=".pdf"]', { timeout: 15000 });
            pdfUrl = await detailPage.evaluate(() => {
                const link = document.querySelector('a[href*="assets.leaflets.schwarz"][href$=".pdf"]');
                return link ? link.href : null;
            });
        } catch (error) {
            // waitForSelector timed out — pdfUrl stays null, handled below.
        }

        if (!pdfUrl) {
            throw new Error('No "Atsisiųsti PDF" link found on the viewer page');
        }

        return { pdfBuffer: await fetchBuffer(pdfUrl), pdfUrl };
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
                const { pdfBuffer, pdfUrl } = await fetchLeafletPdf(browser, slug);

                await submitFlyer({
                    store: 'Lidl',
                    title,
                    catalogName: 'Lidl',
                    issueNumber: weekMatch ? weekMatch[1] : null,
                    validFrom,
                    validTo,
                    pdfBuffer,
                    sourcePdfUrl: pdfUrl,
                    filename: `lidl-${slug}.pdf`,
                    sourceId: slug,
                });
            } catch (error) {
                console.error(`Failed to process leaflet "${title}" (${slug}):`, error.message);
            }
        }
    } finally {
        await browser.close();
    }
})();
