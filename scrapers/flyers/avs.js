import https from 'https';
import { fetchBuffer, submitFlyer, extractCoverInfo, renderFirstPdfPageToJpeg } from './_shared.js';

// avs.lt/akcijos/ is plain server-rendered HTML (no Puppeteer needed) with a
// single current leaflet: a direct PDF link whose own filename and link
// text both already carry the exact validity range
// ("AVS_maisto_2026_08_21_-_2026_09_06__.pdf" / "AVS leidinys 2026 08 21 -
// 2026 09 06 PDF formatas") — no OCR or embed platform involved at all. The
// PDF itself is hosted on a separate mozfiles.com CDN with a normal
// certificate; only www.avs.lt's own certificate (a CloudFront
// misconfiguration on their end — it serves the wrong cert for its own
// hostname) needs a scoped TLS bypass to fetch the listing page's HTML.
const LISTING_URL = 'https://www.avs.lt/akcijos/';

function fetchHtmlIgnoringCert(url) {
    return new Promise((resolve, reject) => {
        https.get(url, { agent: new https.Agent({ rejectUnauthorized: false }) }, res => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve(data));
        }).on('error', reject);
    });
}

function parseDateRange(text) {
    const match = text.match(/(\d{4})\s*(\d{2})\s*(\d{2})\s*[-–]\s*(\d{4})\s*(\d{2})\s*(\d{2})/);
    if (!match) return { validFrom: null, validTo: null };

    const [, fromYear, fromMonth, fromDay, toYear, toMonth, toDay] = match;
    return {
        validFrom: `${fromYear}-${fromMonth}-${fromDay}`,
        validTo: `${toYear}-${toMonth}-${toDay}`,
    };
}

(async () => {
    const html = await fetchHtmlIgnoringCert(LISTING_URL);

    const linkMatch = html.match(/<a href="(https:\/\/site-\d+\.mozfiles\.com\/files\/\d+\/[^"]+\.pdf)">([^<]*)<\/a>/);
    if (!linkMatch) {
        console.log('No PDF link found on', LISTING_URL);
        return;
    }

    const [, pdfUrl, linkText] = linkMatch;
    let { validFrom, validTo } = parseDateRange(linkText);

    try {
        const pdfBuffer = await fetchBuffer(pdfUrl);

        if (!validFrom) {
            const coverImage = await renderFirstPdfPageToJpeg(pdfUrl);
            const coverInfo = await extractCoverInfo({ store: 'AVS', imageBuffer: coverImage, filename: 'avs-cover.jpg' });
            if (coverInfo?.validFrom) {
                validFrom = coverInfo.validFrom;
                validTo = coverInfo.validTo;
            } else {
                console.log('No date range found in link text or cover OCR — submitting without dates');
            }
        }

        await submitFlyer({
            store: 'AVS',
            title: linkText.trim() || 'AVS leidinys',
            catalogName: 'AVS',
            validFrom,
            validTo,
            pdfBuffer,
            sourcePdfUrl: pdfUrl,
            filename: `avs-${pdfUrl.split('/').pop()}`,
            sourceId: pdfUrl,
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
