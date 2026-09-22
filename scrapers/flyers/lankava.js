import { fetchBuffer, imagesToPdf, submitFlyer, extractCoverInfo } from './_shared.js';

// lankava.lt/akcijos is plain server-rendered HTML (OpenCart) with one
// current monthly leaflet as one `.sales_grid .item` block: a title
// ("Lankava akcijų leidinys Rugsėjis"), a self-hosted image gallery
// (`image/discounts/discount_{id}/page_{N}.jpg`, sequential from 0, no
// plain page count anywhere so pages are discovered by probing until a
// request 404s), and plain validity text ("Pasiūlymai galioja rugsėjo
// 1-30 dienomis.") with no year — the page carries no other year signal, so
// the year is taken from the current date at scrape time.
const LISTING_URL = 'https://www.lankava.lt/akcijos';

const MONTHS = {
    sausio: '01', vasario: '02', kovo: '03', balandžio: '04', gegužės: '05', birželio: '06',
    liepos: '07', rugpjūčio: '08', rugsėjo: '09', spalio: '10', lapkričio: '11', gruodžio: '12',
};

function parseDateRange(text) {
    const decoded = text.replace(/&nbsp;/g, ' ');
    const match = decoded.match(/galioja\s+(\S+)\s+(\d{1,2})\s*-\s*(\d{1,2})\s*dienomis/i);
    if (!match) return { validFrom: null, validTo: null };

    const [, monthName, fromDay, toDay] = match;
    const month = MONTHS[monthName.toLowerCase()];
    if (!month) return { validFrom: null, validTo: null };

    const year = new Date().getFullYear();
    return {
        validFrom: `${year}-${month}-${fromDay.padStart(2, '0')}`,
        validTo: `${year}-${month}-${toDay.padStart(2, '0')}`,
    };
}

async function collectPageUrls(discountId) {
    const urls = [];
    for (let n = 0; ; n++) {
        const url = `https://www.lankava.lt/image/discounts/discount_${discountId}/page_${n}.jpg`;
        const response = await fetch(url, { method: 'HEAD' }).catch(() => null);
        if (!response || !response.ok) break;
        urls.push(url);
    }
    return urls;
}

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const items = html.split('<div class="item_title">').slice(1);
    console.log(`Found ${items.length} leaflet item(s) on ${LISTING_URL}`);

    for (const item of items) {
        const titleMatch = item.match(/^([^<]+)</);
        const textMatch = item.match(/<div class="item_text">([\s\S]*?)<\/div>/);
        const discountIdMatch = item.match(/discount_(\d+)/);

        if (!titleMatch || !discountIdMatch) {
            console.log('Skipping item — missing title or discount id');
            continue;
        }

        const title = titleMatch[1].trim();
        const discountId = discountIdMatch[1];
        let { validFrom, validTo } = textMatch ? parseDateRange(textMatch[1]) : { validFrom: null, validTo: null };

        try {
            const imageUrls = await collectPageUrls(discountId);
            if (imageUrls.length === 0) {
                console.log(`No page images found for "${title}" (discount_${discountId})`);
                continue;
            }

            console.log(`Found leaflet "${title}" with ${imageUrls.length} page(s)`);

            const buffers = [];
            for (const url of imageUrls) {
                buffers.push(await fetchBuffer(url));
            }

            if (!validFrom) {
                const coverInfo = await extractCoverInfo({ store: 'Lankava', imageBuffer: buffers[0], filename: 'lankava-cover.jpg' });
                if (coverInfo?.validFrom) {
                    validFrom = coverInfo.validFrom;
                    validTo = coverInfo.validTo;
                } else {
                    console.log(`No date range parsed for "${title}" (page text or cover OCR) — submitting without dates`);
                }
            }

            const pdfBuffer = await imagesToPdf(buffers);

            await submitFlyer({
                store: 'Lankava',
                title,
                catalogName: 'Lankava',
                validFrom,
                validTo,
                pdfBuffer,
                filename: `lankava-${discountId}.pdf`,
                sourceId: discountId,
            });
        } catch (error) {
            console.error(`Failed to process leaflet "${title}":`, error.message);
        }
    }
})();
