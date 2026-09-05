import { launchBrowser, imagesToPdf, submitFlyer, extractCoverInfo, sleep } from './_shared.js';

// aibe.lt/leidinys/ only ever shows the single current issue, embedded via
// an iframe to a FlipHTML5-hosted flipbook (online.fliphtml5.com/{account}/
// {book}/). No validity dates appear anywhere on aibe.lt itself — only on
// the leaflet's own cover page — so those are read via the backend's Gemini
// cover-OCR endpoint instead of guessed from surrounding text.
const LISTING_URL = 'https://aibe.lt/leidinys/';

async function dismissCookieDialog(page) {
    try {
        const button = await page.$('#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll, #CybotCookiebotDialogBodyButtonAccept');
        if (button) {
            await button.click();
            await sleep(500);
        }
    } catch (error) {
        // no dialog present — nothing to do
    }
}

async function findBookUrl(page) {
    const iframeSrc = await page.$eval(
        'iframe[src*="fliphtml5.com"]',
        el => el.getAttribute('src')
    ).catch(() => null);

    const title = await page.$eval(
        'iframe[src*="fliphtml5.com"]',
        el => el.getAttribute('title')
    ).catch(() => null);

    return { bookUrl: iframeSrc, title: title ?? 'Aibė leidinys' };
}

// FlipHTML5 names each page image by a content hash, not a sequence number,
// with no plain page list available (its javascript/config.js is an
// encrypted blob) — so pages are discovered by paging through the viewer
// with the arrow keys while collecting distinct `files/large/{hash}.webp`
// URLs in first-seen order, stopping once several presses in a row surface
// nothing new.
async function collectPageImageUrls(browser, bookUrl) {
    const page = await browser.newPage();
    await page.setViewport({ width: 1400, height: 1000 });

    const seen = new Map();

    page.on('response', response => {
        const match = response.url().match(/files\/large\/[a-f0-9]+\.webp/);
        if (match && !seen.has(match[0])) {
            seen.set(match[0], response.url().split('?')[0]);
        }
    });

    try {
        await page.goto(bookUrl, { waitUntil: 'networkidle2', timeout: 60000 });
        await sleep(1500);
        await dismissCookieDialog(page);
        await sleep(1500);

        let stable = 0;
        for (let i = 0; i < 80 && stable < 5; i++) {
            const before = seen.size;
            await page.keyboard.press('ArrowRight');
            await sleep(900);
            stable = seen.size === before ? stable + 1 : 0;
        }

        return Array.from(seen.values());
    } finally {
        await page.close();
    }
}

// pdf-lib can't embed WEBP, and this project has no image-conversion
// library installed — but Chromium decodes WEBP natively, so navigating a
// page directly to the raw image URL and screenshotting it (viewport sized
// to the image's own natural dimensions, for a 1:1 pixel copy) converts
// WEBP to an embeddable format for free. JPEG rather than PNG — a 24-page
// leaflet's worth of full-resolution PNG screenshots (~3MB each) blows past
// the backend's 60MB upload cap; JPEG at quality 85 keeps the whole PDF
// well under it with no visible quality loss on a product-flyer photo.
async function renderImageToJpeg(browser, imageUrl) {
    const page = await browser.newPage();

    try {
        await page.goto(imageUrl, { waitUntil: 'networkidle2', timeout: 60000 });
        const { width, height } = await page.evaluate(() => {
            const img = document.querySelector('img');
            return { width: img.naturalWidth, height: img.naturalHeight };
        });
        await page.setViewport({ width, height });
        return await page.screenshot({ type: 'jpeg', quality: 85 });
    } finally {
        await page.close();
    }
}

(async () => {
    const browser = await launchBrowser();

    try {
        const page = await browser.newPage();
        await page.goto(LISTING_URL, { waitUntil: 'domcontentloaded' });

        const { bookUrl, title } = await findBookUrl(page);
        if (!bookUrl) {
            console.log('No FlipHTML5 embed found on', LISTING_URL);
            return;
        }

        console.log(`Found leaflet "${title}" at ${bookUrl}`);

        const imageUrls = await collectPageImageUrls(browser, new URL(bookUrl, LISTING_URL).toString());
        console.log(`Collected ${imageUrls.length} page image(s)`);

        if (imageUrls.length === 0) {
            console.log('No page images found — aborting');
            return;
        }

        const buffers = [];
        for (const url of imageUrls) {
            buffers.push(await renderImageToJpeg(browser, url));
        }

        const coverInfo = await extractCoverInfo({ store: 'Aibė', imageBuffer: buffers[0], filename: 'aibe-cover.jpg' });
        if (!coverInfo?.validFrom) {
            console.log('No reliable dates from cover OCR — submitting without dates');
        }

        const issueMatch = title.match(/Nr\.\s*(\d+)/i);
        const pdfBuffer = await imagesToPdf(buffers);

        await submitFlyer({
            store: 'Aibė',
            title: coverInfo?.title || title,
            catalogName: 'Aibė',
            issueNumber: issueMatch ? issueMatch[1] : null,
            validFrom: coverInfo?.validFrom ?? null,
            validTo: coverInfo?.validTo ?? null,
            pdfBuffer,
            filename: `aibe-${issueMatch ? issueMatch[1] : 'leidinys'}.pdf`,
        });
    } finally {
        await browser.close();
    }
})();
