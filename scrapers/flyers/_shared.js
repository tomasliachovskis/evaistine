import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import { PDFDocument } from 'pdf-lib';

puppeteer.use(StealthPlugin());

export const sleep = ms => new Promise(res => setTimeout(res, ms));

export async function launchBrowser() {
    const chromePath = `${process.env.HOME}/.cache/puppeteer/chrome/linux-121.0.6167.85/chrome-linux64/chrome`;
    const launchOptions = {
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
        ],
    };

    const systemChromePath = '/usr/bin/google-chrome-stable';

    if (fs.existsSync(chromePath)) {
        launchOptions.executablePath = chromePath;
    } else if (fs.existsSync(systemChromePath)) {
        launchOptions.executablePath = systemChromePath;
    } else {
        launchOptions.executablePath = puppeteer.executablePath();
    }

    return puppeteer.launch(launchOptions);
}

export async function fetchBuffer(url, init = {}) {
    const response = await fetch(url, init);

    if (!response.ok) {
        throw new Error(`Fetch failed with status ${response.status} for ${url}`);
    }

    return Buffer.from(await response.arrayBuffer());
}

// A "does this page exist" loop that stops on a non-ok status (fetchBuffer
// throwing) can still get fooled by a CDN that answers an out-of-range page
// with 200 and a non-image body instead of a real 404 — confirmed live on
// Issuu's image.isu.pub (iki.js): pdf-lib's JpegEmbedder crashed the whole
// scraper run with "SOI not found in JPEG" on page N+1's response instead
// of the loop just stopping at N like every other store's does. Checking
// the real JPEG magic bytes (0xFFD8) catches that case the same way a 404
// would.
export function isValidJpeg(buffer) {
    return buffer.length > 2 && buffer[0] === 0xFF && buffer[1] === 0xD8;
}

// Merges page images (JPEG or PNG buffers, in order) into a single PDF, one
// full-page image per page — used whenever a store only exposes flyer pages
// as images rather than a downloadable PDF.
export async function imagesToPdf(imageBuffers) {
    const pdfDoc = await PDFDocument.create();

    for (const buffer of imageBuffers) {
        const isPng = buffer[0] === 0x89 && buffer[1] === 0x50 && buffer[2] === 0x4e && buffer[3] === 0x47;
        const image = isPng ? await pdfDoc.embedPng(buffer) : await pdfDoc.embedJpg(buffer);
        const page = pdfDoc.addPage([image.width, image.height]);
        page.drawImage(image, { x: 0, y: 0, width: image.width, height: image.height });
    }

    return Buffer.from(await pdfDoc.save());
}

// Used by renderFirstPdfPageToJpeg() below (cover OCR only). Screenshotting
// Chrome's own built-in PDF viewer is a real race: its `#page=N` navigation
// resolves 'domcontentloaded' almost instantly (that's
// just the viewer shell), well before the PDF's own bytes finish streaming
// in and decoding — a fixed short sleep after that can fire while the
// viewer is still showing its loading chrome (toolbar/sidebar) over a blank
// page. Seen live on Grustė's 34MB "gėrimų gidas": stored and served as a
// blank page under the viewer's own UI. Tried waiting on a network-idle
// signal instead of a fixed sleep first ('networkidle0' and 'networkidle2'
// both) — confirmed live against this exact 32MB file that Chrome's PDF
// viewer keeps some background connection open indefinitely while a large
// file is loading, so navigation just times out every time regardless of
// threshold. So: 'domcontentloaded' (fast, never hangs) plus a generous
// fixed sleep as a first pass, with screenshotIsLikelyBlank() below doing
// the actual work — it measures the real output and retries with a much
// longer wait if that first pass wasn't actually enough, rather than
// trying to guess the right timeout in advance.
function screenshotIsLikelyBlank(buffer, { quality }) {
    // A near-solid-white capture (the viewer's loading state, dark toolbar
    // chrome included) still JPEG-compresses far smaller than one with real
    // page content underneath — confirmed live against Grustė's "gėrimų
    // gidas": a blank first-page capture came to 62KB, the same page once
    // actually rendered came to 1077KB at the same quality/viewport. The
    // toolbar/sidebar chrome alone has enough visual complexity that a much
    // lower threshold (previously quality * 60 =~ 4.8KB) never caught this.
    const MIN_PLAUSIBLE_BYTES = quality * 3000;

    return buffer.length < MIN_PLAUSIBLE_BYTES;
}

// Screenshots just page 1 of a real, directly-downloadable PDF (with the
// Chrome-viewer-race handling above) — used to get a cover image for extractCoverInfo() when a
// scraper never builds its leaflet from a per-page image array to begin
// with (it just fetches one whole PDF file), so there's no buffers[0]
// already lying around.
export async function renderFirstPdfPageToJpeg(pdfUrl, { quality = 80, viewport = { width: 1200, height: 1600, deviceScaleFactor: 2 } } = {}) {
    const browser = await launchBrowser();

    try {
        const page = await browser.newPage();
        await page.setViewport(viewport);
        await page.goto(`${pdfUrl}#page=1&toolbar=0&navpanes=0&scrollbar=0`, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await sleep(6000);

        let screenshot = await page.screenshot({ type: 'jpeg', quality });
        if (screenshotIsLikelyBlank(screenshot, { quality })) {
            await sleep(6000);
            screenshot = await page.screenshot({ type: 'jpeg', quality });
        }

        return screenshot;
    } finally {
        await browser.close();
    }
}

// Asks the backend's Gemini-backed cover-page OCR for a title/valid_from/
// valid_to when a leaflet's listing/detail page gives no date any other way
// (e.g. Aibė's cover-only "Kainos galioja ..." text, with nothing in the
// surrounding page HTML). Returns null on any failure (Gemini not
// configured, no reliable dates found, etc.) — callers should fall back to
// submitting without dates rather than treat this as fatal.
export async function extractCoverInfo({ store, imageBuffer, filename = 'cover.jpg' }) {
    const form = new FormData();
    form.append('store', store);
    form.append('image', new Blob([imageBuffer]), filename);

    try {
        const response = await fetch('http://localhost/api/scrapers/extract-flyer-info', {
            method: 'POST',
            body: form,
        });

        if (!response.ok) return null;

        const data = await response.json();
        return { title: data.title ?? null, validFrom: data.valid_from ?? null, validTo: data.valid_to ?? null };
    } catch (error) {
        console.error('extractCoverInfo failed:', error.message);
        return null;
    }
}

// Posts one flyer (PDF + metadata) to the backend. The backend enforces both
// "skip if expired" and "skip if we already have this document" (by
// source_id when given, else by valid_from/valid_to/title), so scrapers can
// safely re-submit everything they find on every run without tracking any
// state of their own.
export async function submitFlyer({
    store,
    title,
    catalogName,
    issueNumber,
    validFrom,
    validTo,
    pdfBuffer,
    filename = 'leidinys.pdf',
    // The original hosted URL `pdfBuffer` was fetched from, if any. A PDF
    // over 40MB is sent as this URL instead of uploaded, and the backend
    // downloads the original itself. Omit for PDFs built from page images
    // (imagesToPdf), which are never that large.
    sourcePdfUrl,
    // Stable per-document ID from the source platform (Yumpu/Issuu docId,
    // dcatalog guid, resolved PDF URL, issue number, ...) — the backend uses
    // this as the primary dedup key instead of title, which can reword
    // between two scrapes of the same physical leaflet. Omit when the
    // scraper has no natural per-run ID; the backend falls back to matching
    // on title/dates for those.
    sourceId,
}) {
    const form = new FormData();
    form.append('store', store);
    if (title) form.append('title', title);
    if (catalogName) form.append('catalog_name', catalogName);
    if (issueNumber) form.append('issue_number', String(issueNumber));
    if (sourceId) form.append('source_id', String(sourceId));
    // Some leaflets have no determinable end date at all (e.g. Lidl's
    // seasonal "Katalogai") — omit the fields entirely rather than send the
    // literal string "null", which would fail the backend's date validation.
    if (validFrom) form.append('valid_from', validFrom);
    if (validTo) form.append('valid_to', validTo);
    // Some stores' PDFs are print masters near or past the backend's 60MB
    // upload cap (Elimart: 54-65MB). Anything over 40MB goes as a URL when
    // there is one, and the backend downloads the original; it renders the
    // pages itself, so nothing is lost. (This used to screenshot Chrome's
    // PDF viewer page by page instead, which stored the viewer's own
    // background and the same page over and over: Elimart, 2026-10.)
    const SEND_AS_URL_BYTES = 40 * 1024 * 1024;
    const MAX_PDF_BYTES = 60 * 1024 * 1024;
    if (pdfBuffer.length <= SEND_AS_URL_BYTES || (pdfBuffer.length <= MAX_PDF_BYTES && !sourcePdfUrl)) {
        form.append('pdf', new Blob([pdfBuffer], { type: 'application/pdf' }), filename);
    } else if (sourcePdfUrl) {
        console.log(`[${store}] ${title || ''}: PDF is ${(pdfBuffer.length / 1024 / 1024).toFixed(1)}MB, sending its URL for the backend to download`);
        form.append('pdf_source_url', sourcePdfUrl);
    } else {
        console.error(`[${store}] ${title || ''}: PDF is ${(pdfBuffer.length / 1024 / 1024).toFixed(1)}MB, over the backend's 60MB cap and no source URL — skipping`);
        return { skipped: true, reason: 'pdf_too_large' };
    }

    const response = await fetch('http://localhost/api/scrapers/store-flyer', {
        method: 'POST',
        body: form,
    });

    const data = await response.json().catch(() => ({}));
    console.log(`[${store}] ${title || ''} (${validFrom} - ${validTo}): HTTP ${response.status}`, data);

    return data;
}
