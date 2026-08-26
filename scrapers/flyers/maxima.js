import { launchBrowser, fetchBuffer, imagesToPdf, submitFlyer, sleep } from './_shared.js';

const LIST_URL = 'https://www.maxima.lt/leidiniai';

// Maxima's /leidiniai list markup is inconsistent between leaflets (some are
// swiper cards, one is a plain heading block) — rather than depending on a
// specific card class, this walks the DOM generically: find any leaf text
// node matching a date-range pattern, and treat the nearest preceding
// non-trivial leaf as its title, then walk up to the nearest ancestor that
// contains a real link. Verified live against all 3 current leaflet types.
function parseDateRange(text) {
    const [left, right] = text.split(/[–-]/).map(s => s.trim());
    const [year, month, day] = left.split(/\s+/);
    const validFrom = `${year}-${month}-${day}`;

    const rightParts = right.split(/\s+/);
    const validTo = rightParts.length === 1
        ? `${year}-${month}-${rightParts[0].padStart(2, '0')}`
        : `${rightParts[0]}-${rightParts[1]}-${rightParts[2]}`;

    return { validFrom, validTo };
}

async function findLeaflets(page) {
    return page.evaluate(() => {
        const dateRe = /^\d{4}[ .]\d{2}[ .]\d{2}\s*[–-]\s*(\d{4}[ .]\d{2}[ .]\d{2}|\d{2})$/;
        const leaves = Array.from(document.querySelectorAll('main *'))
            .filter(el => el.children.length === 0 && el.textContent.trim().length > 0);
        const results = [];

        for (let i = 0; i < leaves.length; i++) {
            const txt = leaves[i].textContent.trim();
            if (!dateRe.test(txt)) continue;

            let j = i - 1;
            while (j >= 0 && leaves[j].textContent.trim().length < 3) j--;
            if (j < 0) continue;

            let el = leaves[j];
            let link = null;
            for (let k = 0; k < 8 && el; k++) {
                el = el.parentElement;
                if (!el) break;
                const a = el.querySelector('a[href]');
                if (a) { link = a; break; }
            }
            if (!link) continue;

            results.push({
                title: leaves[j].textContent.trim(),
                date: txt,
                href: link.getAttribute('href'),
            });
        }

        return results;
    });
}

// Each leaflet detail page embeds an iPaper.io flipbook. There's no plain
// PDF link in the DOM, but once the viewer loads it requests each page as
// `.../Papers/{guid}/Pages/{n}/Zoom.jpg?token=...&expires=...` — and per
// live testing, that same token/expires pair is valid for every page number,
// not just the one that was requested. So: sniff the first such request,
// then reconstruct + fetch every page 1..N directly (N read off the
// viewer's own "current / total" page counter) instead of driving the
// flipbook UI through every page.
async function fetchLeafletPdf(browser, href) {
    const detailPage = await browser.newPage();
    let firstZoomUrl = null;

    detailPage.on('response', response => {
        if (!firstZoomUrl && /\/Pages\/\d+\/Zoom\.jpg/.test(response.url())) {
            firstZoomUrl = response.url();
        }
    });

    try {
        await detailPage.goto(new URL(href, LIST_URL).toString(), { waitUntil: 'domcontentloaded' });

        for (let waited = 0; waited < 20 && !firstZoomUrl; waited++) {
            await sleep(1000);
        }

        if (!firstZoomUrl) {
            throw new Error('No page image request observed (iPaper viewer did not load in time)');
        }

        // The page counter lives inside the iPaper viewer's iframe, which is
        // cross-origin (viewer.ipaper.io vs maxima.lt) — evaluating against
        // the top-level page's innerText can never see it, only a frame-
        // scoped evaluate can. It can also render a beat after the first
        // page image request fires, so retry rather than trust an early null.
        let counterMatch = null;
        for (let attempt = 0; attempt < 10 && !counterMatch; attempt++) {
            await sleep(1000);
            const viewerFrame = detailPage.frames().find(f => f.url().includes('viewer.ipaper.io'));
            if (!viewerFrame) continue;
            counterMatch = await viewerFrame.evaluate(
                () => document.body.innerText.match(/(\d+)\s*\/\s*(\d+)/)
            );
        }

        if (!counterMatch) {
            throw new Error('Could not read total page count from viewer');
        }

        const totalPages = parseInt(counterMatch[2], 10);

        const buffers = [];
        for (let n = 1; n <= totalPages; n++) {
            const pageUrl = firstZoomUrl.replace(/\/Pages\/\d+\/Zoom\.jpg/, `/Pages/${n}/Zoom.jpg`);
            let buffer = null;

            for (let attempt = 0; attempt < 3 && !buffer; attempt++) {
                try {
                    buffer = await fetchBuffer(pageUrl);
                } catch (error) {
                    console.log(`Retrying Maxima page ${n}/${totalPages} (${error.message})`);
                    await sleep(1000);
                }
            }

            if (!buffer) {
                throw new Error(`Failed to fetch page ${n}/${totalPages}`);
            }

            buffers.push(buffer);
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
        await page.goto(LIST_URL, { waitUntil: 'domcontentloaded' });

        const leaflets = await findLeaflets(page);
        console.log(`Found ${leaflets.length} leaflet(s) on ${LIST_URL}`);

        for (const leaflet of leaflets) {
            const { validFrom, validTo } = parseDateRange(leaflet.date);
            const issueMatch = leaflet.title.match(/Nr\.\s*(\d+)/i);

            try {
                const pdfBuffer = await fetchLeafletPdf(browser, leaflet.href);

                await submitFlyer({
                    store: 'Maxima',
                    title: leaflet.title,
                    issueNumber: issueMatch ? issueMatch[1] : null,
                    validFrom,
                    validTo,
                    pdfBuffer,
                    filename: `maxima-${issueMatch ? issueMatch[1] : leaflet.href.replace(/\W+/g, '-')}.pdf`,
                });
            } catch (error) {
                console.error(`Failed to process leaflet "${leaflet.title}":`, error.message);
            }
        }
    } finally {
        await browser.close();
    }
})();
