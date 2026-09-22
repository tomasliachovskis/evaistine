import { launchBrowser, imagesToPdf, submitFlyer, extractCoverInfo, sleep } from './_shared.js';

// ciamarket.lt/akciju-leidinys/ embeds a FlipHTML5 flipbook directly (same
// platform as Aibė — see aibe.js), with the issue number in a plain
// heading ("ČIA MARKET akcijų leidinys Nr. 18 (93)") but no validity dates
// anywhere on the page — only on the leaflet's own cover, read via the
// backend's Gemini cover-OCR endpoint.
const LISTING_URL = 'https://www.ciamarket.lt/akciju-leidinys/';

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
    return page.$eval('iframe[src*="fliphtml5.com"]', el => el.getAttribute('src')).catch(() => null);
}

// See aibe.js for the full rationale — FlipHTML5 names each page image by a
// content hash with no plain page list available, so pages are discovered
// by paging through the viewer with the arrow keys while collecting
// distinct `files/large/{hash}.webp` URLs in first-seen order.
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

// Chromium decodes WEBP natively (no image-conversion library in this
// project) — navigating directly to the raw image URL and screenshotting
// it (viewport sized to the image's own natural dimensions) converts it to
// JPEG for free, keeping the whole PDF well under the backend's 60MB cap.
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

        const bookUrl = await findBookUrl(page);
        if (!bookUrl) {
            console.log('No FlipHTML5 embed found on', LISTING_URL);
            return;
        }

        const heading = await page.$eval('h2', el => el.textContent.trim()).catch(() => 'ČIA MARKET akcijų leidinys');
        console.log(`Found leaflet "${heading}" at ${bookUrl}`);

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

        const coverInfo = await extractCoverInfo({ store: 'Čia', imageBuffer: buffers[0], filename: 'cia-cover.jpg' });
        if (!coverInfo?.validFrom) {
            console.log('No reliable dates from cover OCR — submitting without dates');
        }

        const issueMatch = heading.match(/Nr\.\s*(\d+)/i);
        const pdfBuffer = await imagesToPdf(buffers);

        await submitFlyer({
            store: 'Čia',
            title: coverInfo?.title || heading,
            catalogName: 'Čia',
            issueNumber: issueMatch ? issueMatch[1] : null,
            validFrom: coverInfo?.validFrom ?? null,
            validTo: coverInfo?.validTo ?? null,
            pdfBuffer,
            filename: `cia-${issueMatch ? issueMatch[1] : 'leidinys'}.pdf`,
            sourceId: issueMatch ? issueMatch[1] : null,
        });
    } finally {
        await browser.close();
    }
})();
