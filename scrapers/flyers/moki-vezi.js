import { fetchBuffer, imagesToPdf, submitFlyer, extractCoverInfo } from './_shared.js';

// mokivezi.lt/leidiniai is plain server-rendered HTML embedding a single
// Publitas-hosted flipbook (view.publitas.com/{account}/{slug}/). Publitas
// exposes a public spreads.json with every page's image URLs (at various
// resolutions) plus that page's own OCR'd text — page 1's text already
// carries "Nr. 9 2026 08 20 – 09 15", so no cover-OCR round trip is needed
// here.
const LISTING_URL = 'https://mokivezi.lt/leidiniai';

function parseDateRange(text) {
    const match = text.match(/Nr\.\s*(\d+)\s*(\d{4})\s*(\d{2})\s*(\d{2})\s*[–-]\s*(\d{2})\s*(\d{2})/);
    if (!match) return { issueNumber: null, validFrom: null, validTo: null };

    const [, issueNumber, year, fromMonth, fromDay, toMonth, toDay] = match;
    return {
        issueNumber,
        validFrom: `${year}-${fromMonth}-${fromDay}`,
        validTo: `${year}-${toMonth}-${toDay}`,
    };
}

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const embedMatch = html.match(/(?:https:)?\/\/view\.publitas\.com\/([a-zA-Z0-9_-]+\/[a-zA-Z0-9_-]+)\//);
    if (!embedMatch) {
        console.log('No Publitas embed found on', LISTING_URL);
        return;
    }

    const publicationPath = embedMatch[1];
    const spreadsUrl = `https://view.publitas.com/${publicationPath}/spreads.json`;
    console.log(`Found Publitas publication: ${publicationPath}`);

    const spreads = await (await fetch(spreadsUrl)).json();
    const allPages = spreads.flatMap(spread => spread.pages);

    if (allPages.length === 0) {
        console.log('No pages found in spreads.json');
        return;
    }

    let { issueNumber, validFrom, validTo } = parseDateRange(allPages[0].text || '');

    const buffers = [];
    for (const page of allPages) {
        buffers.push(await fetchBuffer(`https://view.publitas.com${page.images.at1600}`));
    }

    if (!validFrom) {
        const coverInfo = await extractCoverInfo({ store: 'Moki Veži', imageBuffer: buffers[0], filename: 'moki-vezi-cover.jpg' });
        if (coverInfo?.validFrom) {
            validFrom = coverInfo.validFrom;
            validTo = coverInfo.validTo;
        } else {
            console.log('No date range found in page 1 text or cover OCR — submitting without dates');
        }
    }

    try {
        const pdfBuffer = await imagesToPdf(buffers);

        await submitFlyer({
            store: 'Moki Veži',
            title: issueNumber ? `Moki Veži kainynas Nr. ${issueNumber}` : 'Moki Veži kainynas',
            catalogName: 'Moki Veži',
            issueNumber,
            validFrom,
            validTo,
            pdfBuffer,
            filename: `moki-vezi-${issueNumber || 'leidinys'}.pdf`,
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
