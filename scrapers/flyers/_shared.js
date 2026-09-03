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

// Pushes a just-saved PDF straight to production over SSH, the same way
// deploy.sh's rsync does (same server, same key, same relative path under
// storage/app/public/) — so a new leaflet reaches production within seconds
// instead of waiting for the next full deploy. deploy.sh's own rsync still
// covers this path too, as a fallback for whenever this fails (offline VPN,
// key not present, transient network error) — it's logged, not thrown.
async function syncPdfToProduction(relativePath) {
    const localPath = path.join(PROJECT_ROOT, 'storage', 'app', 'public', relativePath);
    const remotePath = `${PROD_REMOTE_DIR}/storage/app/public/${relativePath}`;
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
        console.log(`Synced ${relativePath} to production.`);
    } catch (error) {
        console.error(`Failed to sync ${relativePath} to production (will fall back to next deploy.sh run):`, error.message);
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
}) {
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

    const response = await fetch('http://127.0.0.1/api/scrapers/store-flyer', {
        method: 'POST',
        body: form,
    });

    const data = await response.json().catch(() => ({}));
    console.log(`[${store}] ${title || ''} (${validFrom} - ${validTo}): HTTP ${response.status}`, data);

    if (response.status === 201 && data?.slug && data?.store?.slug) {
        await syncPdfToProduction(`flyers/pdfs/${data.store.slug}-${data.slug}.pdf`);
    }

    return data;
}
