import { fetchBuffer, submitFlyer } from './_shared.js';

// thomas-philipps.lt/leidinys is plain server-rendered HTML with a single
// current weekly leaflet, a real direct PDF link, and validity dates as
// plain text: "Savaitės pasiūlymai 2026.08.31–09.06" — the end date is a
// partial "MM.DD" that shares the start date's year (and, per CLAUDE.md's
// own note on this store, sometimes shares the month too).
const LISTING_URL = 'https://www.thomas-philipps.lt/leidinys';

function parseDateRange(text) {
    const match = text.match(/(\d{4})\.(\d{2})\.(\d{2})\s*[–-]\s*(\d{2})(?:\.(\d{2}))?/);
    if (!match) return { validFrom: null, validTo: null };

    const [, year, startMonth, startDay, a, b] = match;
    const validFrom = `${year}-${startMonth}-${startDay}`;

    // Two digit groups after the dash: "MM.DD" (b present) uses that month;
    // one digit group: "DD" (only a) shares the start month.
    const validTo = b ? `${year}-${a}-${b}` : `${year}-${startMonth}-${a}`;

    return { validFrom, validTo };
}

(async () => {
    const html = await (await fetch(LISTING_URL)).text();

    const pdfMatch = html.match(/href="(https:\/\/www\.thomas-philipps\.lt\/wp-content\/uploads\/[^"]+\.pdf)"/);
    if (!pdfMatch) {
        console.log('No PDF link found on', LISTING_URL);
        return;
    }

    const dateMatch = html.match(/Savaitės pasiūlymai<br\s*\/?>\s*([^<]+)/);
    const { validFrom, validTo } = dateMatch ? parseDateRange(dateMatch[1]) : { validFrom: null, validTo: null };

    if (!validFrom) {
        console.log('No date range found — submitting without dates');
    }

    try {
        const pdfBuffer = await fetchBuffer(pdfMatch[1]);

        await submitFlyer({
            store: 'Thomas Philipps',
            title: 'Thomas Philipps savaitės leidinys',
            catalogName: 'Thomas Philipps',
            validFrom,
            validTo,
            pdfBuffer,
            sourcePdfUrl: pdfMatch[1],
            filename: `thomas-philipps-${pdfMatch[1].split('/').pop()}`,
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
