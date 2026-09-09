import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { execFile } from 'child_process';
import { promisify } from 'util';
import { PDFDocument } from 'pdf-lib';

puppeteer.use(StealthPlugin());

const execFileAsync = promisify(execFile);

// Repo root — this file lives at scrapers/flyers/_shared.js.
const PROJECT_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');

// Same target as deploy.sh's rsync — kept in sync manually since this is
// the same one-off VPS deploy.sh already hardcodes.
const PROD_SERVER = 'deploy@84.247.186.143';
const PROD_REMOTE_DIR = '/var/www/api';

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

// Pushes a local file straight to production over SSH/rsync — same server
// and key deploy.sh already hardcodes. Used for two independent targets
// after a successful submitFlyer (see below): the public/flyers/pdfs copy
// (deploy.sh's own rsync covers that path too, as a fallback for whenever
// this fails — offline VPN, key not present, transient network error) and
// the flyers-incoming copy (deploy.sh has no fallback for that one — it only
// rsyncs the repo's own storage/app/flyers-incoming, which is normally
// empty locally, so this SSH push is the only way that file reaches
// production short of a manual copy). Logged, not thrown, either way.
async function syncFileToProduction(localPath, remotePath) {
    const remoteDir = path.dirname(remotePath).replace(/\\/g, '/');

    const deployKeyPath = path.join(PROJECT_ROOT, 'deploy_key');
    const sshOpts = ['-o', 'StrictHostKeyChecking=no', '-o', 'UserKnownHostsFile=/dev/null'];

    if (fs.existsSync(deployKeyPath)) {
        fs.chmodSync(deployKeyPath, 0o600);
        sshOpts.unshift('-i', deployKeyPath);
    }

    const sshCommand = `ssh ${sshOpts.join(' ')}`;

    try {
        await execFileAsync('ssh', [...sshOpts, PROD_SERVER, `mkdir -p '${remoteDir}'`]);
        await execFileAsync('rsync', ['-avz', '-e', sshCommand, localPath, `${PROD_SERVER}:${remotePath}`]);
        console.log(`Synced ${remotePath} to production.`);
        return true;
    } catch (error) {
        console.error(`Failed to sync ${remotePath} to production:`, error.message);
        return false;
    }
}

async function syncPdfToProduction(relativePath) {
    const localPath = path.join(PROJECT_ROOT, 'storage', 'app', 'public', relativePath);
    const remotePath = `${PROD_REMOTE_DIR}/storage/app/public/${relativePath}`;
    return syncFileToProduction(localPath, remotePath);
}

// Rebuilds an oversized source PDF (over the backend's 60MB cap — see
// submitFlyer below) into a much smaller one by rasterizing each page as a
// JPEG and reassembling, rather than just skipping it. Works for any PDF
// Chromium can render inline (no Content-Disposition:attachment) — same
// technique as the cover-only screenshot used elsewhere (e.g. kubas.js),
// just driven page-by-page via the `#page=N` viewer fragment. pdf-lib reads
// the page count without needing to rasterize anything itself.
//
// Screenshotting Chrome's own built-in PDF viewer is a real race: its
// `#page=N` navigation resolves 'domcontentloaded' almost instantly (that's
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

export async function compressPdfByRasterizing(pdfUrl, pdfBuffer, { quality = 80, viewport = { width: 1200, height: 1600, deviceScaleFactor: 2 } } = {}) {
    const pageCount = (await PDFDocument.load(pdfBuffer, { ignoreEncryption: true })).getPageCount();
    const browser = await launchBrowser();

    try {
        const page = await browser.newPage();
        await page.setViewport(viewport);

        const buffers = [];
        for (let n = 1; n <= pageCount; n++) {
            // toolbar=0/navpanes=0/scrollbar=0 are standard PDF-open params
            // Chrome's built-in viewer also honors — without them every
            // screenshot includes the viewer's own toolbar and thumbnail
            // sidebar baked permanently into the page image.
            await page.goto(`${pdfUrl}#page=${n}&toolbar=0&navpanes=0&scrollbar=0`, { waitUntil: 'domcontentloaded', timeout: 30000 });
            await sleep(n === 1 ? 6000 : 3000);

            let screenshot = await page.screenshot({ type: 'jpeg', quality });
            if (screenshotIsLikelyBlank(screenshot, { quality })) {
                console.log(`Page ${n}/${pageCount} looks blank (still rendering?) — waiting longer and retrying once`);
                await sleep(6000);
                screenshot = await page.screenshot({ type: 'jpeg', quality });
            }

            buffers.push(screenshot);
        }

        return imagesToPdf(buffers);
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
        const response = await fetch('http://127.0.0.1/api/scrapers/extract-flyer-info', {
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
// "skip if expired" and "skip if we already have this exact valid_from/
// valid_to for this store", so scrapers can safely re-submit everything they
// find on every run without tracking any state of their own.
export async function submitFlyer({
    store,
    title,
    catalogName,
    issueNumber,
    validFrom,
    validTo,
    pdfBuffer,
    filename = 'leidinys.pdf',
    // The original hosted URL `pdfBuffer` was fetched from, if any — needed
    // to rasterize-and-shrink an oversized PDF (Chromium re-renders it page
    // by page from this URL). Omit for PDFs already built from page images
    // (imagesToPdf), which are never this large to begin with.
    sourcePdfUrl,
}) {
    // A handful of stores' source PDFs are high-res print masters past the
    // backend's 60MB hard cap (first hit: Elimart, 61MB) — rasterize and
    // rebuild as a much smaller JPEG-based PDF only for those, rather than
    // reject or silently fail. Screenshotting each page is a real race
    // (Chromium's own PDF viewer can still be mid-render when the
    // screenshot fires, capturing its loading chrome instead of the page —
    // seen live on Grustė's 34MB "gėrimų gidas": a blank white page under
    // the viewer's own toolbar/sidebar, stored and served as if it were
    // real content), so this is worth avoiding whenever the original
    // genuinely already fits — 40MB (not 20MB) leaves real buffer under the
    // 60MB cap while letting every file between 20-40MB pass through as its
    // real, correct self instead of risking that race for no reason.
    const RESIZE_THRESHOLD_BYTES = 40 * 1024 * 1024;
    if (pdfBuffer.length > RESIZE_THRESHOLD_BYTES && sourcePdfUrl) {
        console.log(`[${store}] ${title || ''}: PDF is ${(pdfBuffer.length / 1024 / 1024).toFixed(1)}MB — rasterizing to shrink it`);
        try {
            pdfBuffer = await compressPdfByRasterizing(sourcePdfUrl, pdfBuffer);
            console.log(`[${store}] ${title || ''}: rasterized down to ${(pdfBuffer.length / 1024 / 1024).toFixed(1)}MB`);
        } catch (error) {
            console.error(`[${store}] ${title || ''}: rasterizing failed (${error.message}) — submitting original`);
        }
    }

    const form = new FormData();
    form.append('store', store);
    if (title) form.append('title', title);
    if (catalogName) form.append('catalog_name', catalogName);
    if (issueNumber) form.append('issue_number', String(issueNumber));
    // Some leaflets have no determinable end date at all (e.g. Lidl's
    // seasonal "Katalogai") — omit the fields entirely rather than send the
    // literal string "null", which would fail the backend's date validation.
    if (validFrom) form.append('valid_from', validFrom);
    if (validTo) form.append('valid_to', validTo);
    form.append('pdf', new Blob([pdfBuffer], { type: 'application/pdf' }), filename);

    // The backend's `max:61440` (60MB) file-size validation rejects a
    // request expecting JSON with a redirect instead of a 422 — which
    // otherwise surfaces here as a silent-looking "HTTP 200 {}" that's easy
    // to mistake for success. Check client-side first for a clear failure —
    // reachable if rasterizing above didn't happen (no sourcePdfUrl) or
    // didn't get it under the cap.
    const MAX_PDF_BYTES = 60 * 1024 * 1024;
    if (pdfBuffer.length > MAX_PDF_BYTES) {
        console.error(`[${store}] ${title || ''}: PDF is ${(pdfBuffer.length / 1024 / 1024).toFixed(1)}MB, over the backend's 60MB cap — skipping`);
        return { skipped: true, reason: 'pdf_too_large' };
    }

    const response = await fetch('http://127.0.0.1/api/scrapers/store-flyer', {
        method: 'POST',
        body: form,
    });

    const data = await response.json().catch(() => ({}));
    console.log(`[${store}] ${title || ''} (${validFrom} - ${validTo}): HTTP ${response.status}`, data);

    if (response.status === 201 && data?.slug && data?.store?.slug) {
        // storage/app/public/flyers/pdfs/ is the only copy production needs
        // now — flyers:process-discounts (Kernel.php, every 5 min) reads
        // discounts straight from the StoreFlyer's own pdf_url, and
        // flyers:process-pages reads the same file for the /leidinys
        // page-viewer. No second copy in storage/app/flyers-incoming/
        // needed anymore.
        await syncPdfToProduction(`flyers/pdfs/${data.store.slug}-${data.slug}.pdf`);
    }

    return data;
}
