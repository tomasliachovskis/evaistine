import { fetchBuffer, submitFlyer, extractCoverInfo, renderFirstPdfPageToJpeg } from './_shared.js';

// gruste.lt/leidiniai is plain server-rendered HTML listing several
// concurrent publications (a main weekly leidinys plus themed ones like
// "Pigiau Nerasi" and a drinks guide), each as one `publication-block` div
// with both a direct PDF download link AND an Issuu flipbook embed — we
// only need the PDF link. Validity dates are plain text right in each
// block's own heading: "Leidinys Nr. 25 2026.09.01 - 09.10" (end date is a
// partial "MM.DD" sharing the start date's year).
const LISTING_URL = 'https://gruste.lt/leidiniai';
const BASE_URL = 'https://gruste.lt';

function parseDateRange(text) {
    const match = text.match(/(\d{4})\.(\d{2})\.(\d{2})\s*-\s*(\d{2})\.(\d{2})/);
    if (!match) return { validFrom: null, validTo: null };

    const [, year, startMonth, startDay, endMonth, endDay] = match;
    return {
        validFrom: `${year}-${startMonth}-${startDay}`,
        validTo: `${year}-${endMonth}-${endDay}`,
    };
}

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const blocks = html.split('<div class="row publication-block">').slice(1);
    console.log(`Found ${blocks.length} publication block(s) on ${LISTING_URL}`);

    for (const block of blocks) {
        const headingMatch = block.match(/<h2>\s*([^<]+?)\s*<div/);
        const pdfMatch = block.match(/href="(\/files\/leidiniai\/[^"]+\.pdf)"/);

        if (!headingMatch || !pdfMatch) {
            console.log('Skipping block — missing heading or PDF link');
            continue;
        }

        const heading = headingMatch[1].trim();
        const pdfUrl = BASE_URL + pdfMatch[1];
        let { validFrom, validTo } = parseDateRange(heading);

        try {
            const pdfBuffer = await fetchBuffer(pdfUrl);

            if (!validFrom) {
                const coverImage = await renderFirstPdfPageToJpeg(pdfUrl);
                const coverInfo = await extractCoverInfo({ store: 'Grustė', imageBuffer: coverImage, filename: 'gruste-cover.jpg' });
                if (coverInfo?.validFrom) {
                    validFrom = coverInfo.validFrom;
                    validTo = coverInfo.validTo;
                } else {
                    console.log(`No date range parsed from heading "${heading}" or cover OCR — submitting without dates`);
                }
            }

            await submitFlyer({
                store: 'Grustė',
                title: heading,
                catalogName: 'Grustė',
                validFrom,
                validTo,
                pdfBuffer,
                sourcePdfUrl: pdfUrl,
                filename: `gruste-${pdfMatch[1].split('/').pop()}`,
            });
        } catch (error) {
            console.error(`Failed to process leaflet "${heading}" (${pdfUrl}):`, error.message);
        }
    }
})();
