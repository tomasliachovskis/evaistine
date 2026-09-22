import { fetchBuffer, submitFlyer, extractCoverInfo, renderFirstPdfPageToJpeg } from './_shared.js';

// bikuva.lt/info/23-leidiniai embeds a self-hosted PrestaShop "dFlip"
// flipbook module (`_df_book` div with a `source="/modules/lpsflipbook/
// views/pdf/{file}.pdf"` attribute) pointing straight at a real PDF — no
// page-by-page scraping needed. The title only gives a month range
// ("Leidinys: Liepa - Rugpjūtis 2026"), which is parsed to a first/last-day
// date range; if the site hasn't been updated for the current period this
// will resolve to a past range and the backend's own expiry check skips it,
// which correctly reflects that nothing current is actually being shown.
const LISTING_URL = 'https://bikuva.lt/info/23-leidiniai';

const MONTHS = {
    sausis: '01', vasaris: '02', kovas: '03', balandis: '04', gegužė: '05', birželis: '06',
    liepa: '07', rugpjūtis: '08', rugsėjis: '09', spalis: '10', lapkritis: '11', gruodis: '12',
};

const LAST_DAY = { '01': 31, '02': 28, '03': 31, '04': 30, '05': 31, '06': 30, '07': 31, '08': 31, '09': 30, '10': 31, '11': 30, '12': 31 };

function parseMonthRange(text) {
    const match = text.match(/Leidinys:\s*(\S+)\s*-\s*(\S+)\s*(\d{4})/i);
    if (!match) return { validFrom: null, validTo: null };

    const [, fromName, toName, year] = match;
    const fromMonth = MONTHS[fromName.toLowerCase()];
    const toMonth = MONTHS[toName.toLowerCase()];
    if (!fromMonth || !toMonth) return { validFrom: null, validTo: null };

    return {
        validFrom: `${year}-${fromMonth}-01`,
        validTo: `${year}-${toMonth}-${LAST_DAY[toMonth]}`,
    };
}

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const bookDivMatch = html.match(/<div[^>]*class="_df_book[^"]*"[^>]*>/);
    if (!bookDivMatch) {
        console.log('No dFlip embed found on', LISTING_URL);
        return;
    }

    const bookDiv = bookDivMatch[0];
    const sourceMatch = bookDiv.match(/source="([^"]+\.pdf)"/);
    const titleMatch = bookDiv.match(/title="([^"]*)"/);

    if (!sourceMatch) {
        console.log('No PDF source found in dFlip embed on', LISTING_URL);
        return;
    }

    const pdfUrl = new URL(sourceMatch[1], LISTING_URL).toString();
    const title = titleMatch ? titleMatch[1] : '';
    let { validFrom, validTo } = parseMonthRange(title);

    try {
        const pdfBuffer = await fetchBuffer(pdfUrl);

        if (!validFrom) {
            const coverImage = await renderFirstPdfPageToJpeg(pdfUrl);
            const coverInfo = await extractCoverInfo({ store: 'Bikuva', imageBuffer: coverImage, filename: 'bikuva-cover.jpg' });
            if (coverInfo?.validFrom) {
                validFrom = coverInfo.validFrom;
                validTo = coverInfo.validTo;
            } else {
                console.log(`Could not parse a month range from "${title}" or cover OCR — submitting without dates`);
            }
        }

        await submitFlyer({
            store: 'Bikuva',
            title: 'Bikuva leidinys',
            catalogName: 'Bikuva',
            validFrom,
            validTo,
            pdfBuffer,
            sourcePdfUrl: pdfUrl,
            filename: 'bikuva-leidinys.pdf',
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
