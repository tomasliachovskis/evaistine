import { fetchBuffer, submitFlyer } from './_shared.js';

// vynoteka.lt's homepage links to a single current price leaflet at
// /nr-{N}-kainos-galioja-{from}-{to} — dates already embedded in the URL
// slug itself (see CLAUDE.md's own note on this store). That page renders a
// 3dflipbook viewer, but the viewer's own container div carries the raw
// source PDF directly in its `src` attribute, so no flipbook driving is
// needed.
const HOME_URL = 'https://vynoteka.lt/';

(async () => {
    const homeHtml = await (await fetch(HOME_URL)).text();

    const linkMatch = homeHtml.match(/href="(\/nr-(\d+)-kainos-galioja-(\d{4})-(\d{2})-(\d{2})-(\d{4})-(\d{2})-(\d{2}))"/);
    if (!linkMatch) {
        console.log('No current price leaflet link found on', HOME_URL);
        return;
    }

    const [, path, issueNumber, fy, fm, fd, ty, tm, td] = linkMatch;
    const validFrom = `${fy}-${fm}-${fd}`;
    const validTo = `${ty}-${tm}-${td}`;
    const detailUrl = new URL(path, HOME_URL).toString();

    const detailHtml = await (await fetch(detailUrl)).text();
    const pdfMatch = detailHtml.match(/class="flip-book-container1" src="([^"]+\.pdf)"/);

    if (!pdfMatch) {
        console.log('No PDF source found on', detailUrl);
        return;
    }

    const pdfUrl = new URL(pdfMatch[1], HOME_URL).toString();

    try {
        const pdfBuffer = await fetchBuffer(pdfUrl);

        await submitFlyer({
            store: 'Vynoteka',
            title: `Vynoteka kainų leidinys Nr. ${issueNumber}`,
            catalogName: 'Vynoteka',
            issueNumber,
            validFrom,
            validTo,
            pdfBuffer,
            sourcePdfUrl: pdfUrl,
            filename: `vynoteka-nr-${issueNumber}.pdf`,
        });
    } catch (error) {
        console.error('Failed to process leaflet:', error.message);
    }
})();
