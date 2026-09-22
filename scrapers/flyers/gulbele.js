import { fetchBuffer, imagesToPdf, submitFlyer, extractCoverInfo } from './_shared.js';

// gulbele.lt/leidiniai (separate from the discount scraper's
// /sumazinta-kaina target) is plain server-rendered HTML: a card per
// leaflet, each with a lightbox image gallery of the leaflet's own pages
// (`a.js-smartPhoto[data-group="album-N"]` hrefs, one per page) and a plain
// "YYYY-MM-DD - YYYY-MM-DD" validity range right in the card text — no PDF,
// no third-party flipbook platform. The page sometimes also lists a
// co-branded "Aibės leidinys" card (Aibė's own leaflet, already handled by
// aibe.js under the "Aibė" store) — skip any card whose title mentions Aibė
// so it isn't double-submitted under the wrong store name.
const LISTING_URL = 'https://gulbele.lt/leidiniai';
const BASE_URL = 'https://gulbele.lt';

function parseDateRange(text) {
    const match = text.match(/(\d{4}-\d{2}-\d{2})\s*-\s*(\d{4}-\d{2}-\d{2})/);
    if (!match) return { validFrom: null, validTo: null };
    return { validFrom: match[1], validTo: match[2] };
}

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const cards = html.split('<div class="card">').slice(1);
    console.log(`Found ${cards.length} card(s) on ${LISTING_URL}`);

    for (const card of cards) {
        const titleMatch = card.match(/<h5 class="card-title">[\s\S]*?<a href="[^"]*">\s*([^<]+?)\s*<\/a>/);
        const dateMatch = card.match(/<p class="card-text">([^<]+)<\/p>/);
        const imageUrls = Array.from(new Set([...card.matchAll(/href="(\/img\/publications\/[a-f0-9]+\.jpg)"/g)].map(m => m[1])));

        if (!titleMatch) {
            console.log('Skipping card — no title found');
            continue;
        }

        const title = titleMatch[1].trim();

        if (/aib[eė]/i.test(title)) {
            console.log(`Skipping "${title}" — belongs to Aibė, not Gulbelė`);
            continue;
        }

        if (imageUrls.length === 0) {
            console.log(`Skipping "${title}" — no page images found`);
            continue;
        }

        let { validFrom, validTo } = dateMatch ? parseDateRange(dateMatch[1]) : { validFrom: null, validTo: null };

        console.log(`Found leaflet "${title}" with ${imageUrls.length} page(s)`);

        try {
            const buffers = [];
            for (const url of imageUrls) {
                buffers.push(await fetchBuffer(BASE_URL + url));
            }

            if (!validFrom) {
                const coverInfo = await extractCoverInfo({ store: 'Gulbelė', imageBuffer: buffers[0], filename: 'gulbele-cover.jpg' });
                if (coverInfo?.validFrom) {
                    validFrom = coverInfo.validFrom;
                    validTo = coverInfo.validTo;
                } else {
                    console.log(`No date range parsed for "${title}" (card text or cover OCR) — submitting without dates`);
                }
            }

            const pdfBuffer = await imagesToPdf(buffers);
            const issueMatch = title.match(/nr\.?\s*(\d+)/i);

            await submitFlyer({
                store: 'Gulbelė',
                title,
                catalogName: 'Gulbelė',
                issueNumber: issueMatch ? issueMatch[1] : null,
                validFrom,
                validTo,
                pdfBuffer,
                filename: `gulbele-${issueMatch ? issueMatch[1] : 'leidinys'}.pdf`,
            });
        } catch (error) {
            console.error(`Failed to process leaflet "${title}":`, error.message);
        }
    }
})();
