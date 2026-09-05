import { launchBrowser, fetchBuffer, imagesToPdf, submitFlyer } from './_shared.js';

// The store page only lists leaflet tiles; each links out to a separate
// leidiniai.rimi.lt/{year}/{section}/{slug}/ detail page that embeds an
// iPaper flipbook.
const LISTING_URL = 'https://www.rimi.lt/asortimentas/rimi-savaitinis-leidinys';

async function findLeaflets(page) {
    return page.$$eval('.sortable-grid__item a[href*="leidiniai.rimi.lt"]', links => links.map(a => ({
        href: a.getAttribute('href'),
        title: a.querySelector('h3')?.textContent.trim() ?? a.getAttribute('title') ?? '',
    })));
}

// Detail pages are plain server-rendered HTML embedding a
// `window.staticSettings` JS object with everything needed to fetch the raw
// page images directly — no need to drive the flipbook UI at all:
// - `name`/`pageTitle` carries the leaflet title, with a trailing
//   "(YYYY.MM.DD - YYYY.MM.DD)" date range on weekly leaflets only (themed
//   catalogs like "Grožio šventė" have no dates — submit without them then).
// - `pages` is an array of page numbers (its length is the page count).
// - `aws.url` is the `.../iPaper/Papers/{guid}/` base, and `aws.policy` is a
//   signed `token=...&token_path=...&expires=...` query string valid for
//   every page under that guid, confirmed live (page 1 and the last page
//   both 200 with the same policy, one past the last page 404).
async function fetchLeafletData(href) {
    const response = await fetch(href);
    if (!response.ok) throw new Error(`Fetch failed with status ${response.status} for ${href}`);
    const html = await response.text();

    const nameMatch = html.match(/"pageTitle":"((?:[^"\\]|\\.)*)"/);
    const pagesMatch = html.match(/"pages":\[([\d,]+)\]/);
    const awsUrlMatch = html.match(/"aws":\{"url":"((?:[^"\\]|\\.)*)"/);
    const policyMatch = html.match(/"policy":"((?:[^"\\]|\\.)*)"/);

    if (!nameMatch || !pagesMatch || !awsUrlMatch || !policyMatch) {
        throw new Error('Could not find window.staticSettings fields on detail page');
    }

    const unescape = s => s.replace(/\\u0026/g, '&').replace(/\\\//g, '/').replace(/\\"/g, '"');

    return {
        title: unescape(nameMatch[1]),
        pageCount: pagesMatch[1].split(',').length,
        awsUrl: unescape(awsUrlMatch[1]),
        policy: unescape(policyMatch[1]),
    };
}

function parseDateRange(title) {
    const match = title.match(/\((\d{4})\.(\d{2})\.(\d{2})\s*-\s*(\d{4})\.(\d{2})\.(\d{2})\)/);
    if (!match) return { validFrom: null, validTo: null };

    return {
        validFrom: `${match[1]}-${match[2]}-${match[3]}`,
        validTo: `${match[4]}-${match[5]}-${match[6]}`,
    };
}

async function fetchLeafletPdf(awsUrl, policy, pageCount) {
    const buffers = [];

    for (let n = 1; n <= pageCount; n++) {
        buffers.push(await fetchBuffer(`${awsUrl}Pages/${n}/Zoom.jpg?${policy}`));
    }

    return imagesToPdf(buffers);
}

(async () => {
    const browser = await launchBrowser();

    try {
        const page = await browser.newPage();
        await page.goto(LISTING_URL, { waitUntil: 'domcontentloaded' });

        const leaflets = await findLeaflets(page);
        console.log(`Found ${leaflets.length} leaflet(s) on ${LISTING_URL}`);

        for (const leaflet of leaflets) {
            if (!leaflet.href) continue;

            try {
                const { title, pageCount, awsUrl, policy } = await fetchLeafletData(leaflet.href);
                const { validFrom, validTo } = parseDateRange(title);
                if (!validFrom) {
                    console.log(`No date range for "${title}" — submitting without dates`);
                }

                const issueMatch = title.match(/Nr\.\s*(\d+)/i);
                const pdfBuffer = await fetchLeafletPdf(awsUrl, policy, pageCount);

                await submitFlyer({
                    store: 'Rimi',
                    title,
                    catalogName: 'Rimi',
                    issueNumber: issueMatch ? issueMatch[1] : null,
                    validFrom,
                    validTo,
                    pdfBuffer,
                    filename: `rimi-${leaflet.href.replace(/\W+/g, '-').replace(/^-+|-+$/g, '')}.pdf`,
                });
            } catch (error) {
                console.error(`Failed to process leaflet "${leaflet.title}" (${leaflet.href}):`, error.message);
            }
        }
    } finally {
        await browser.close();
    }
})();
