// nuolaidos.lt is a Next.js SSR site — no Puppeteer needed at all, a plain
// fetch() with a real browser User-Agent returns the exact same HTML a
// browser would get (verified against a live Chrome load during planning).
// Node's own default UA gets blocked by whatever bot-detection sits in
// front of the site; a normal browser UA string sails through.
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

// Each /{slug}-darbo-laikas page embeds a complete workingHours JSON array
// for every location of that chain directly in the page's Next.js RSC
// flight-data script tag — one page load gets everything, no need to visit
// each location's own sub-page.
export async function extractWorkingHours(slug) {
    const response = await fetch(`https://www.nuolaidos.lt/${slug}-darbo-laikas`, {
        headers: { 'User-Agent': USER_AGENT },
        signal: AbortSignal.timeout(15000),
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const text = await response.text();

    if (!text.includes('workingHours')) {
        return [];
    }

    // Embedded as a JS string literal inside the RSC flight chunk, so quotes
    // appear backslash-escaped in the raw HTML (\"workingHours\": rather
    // than "workingHours":) — search and bracket-match on that escaped form,
    // then unescape just the extracted slice before parsing.
    const marker = '\\"workingHours\\":[';
    const startIdx = text.indexOf(marker);

    if (startIdx === -1) {
        return [];
    }

    const arrayStart = startIdx + marker.length - 1;
    let depth = 0;
    let inString = false;
    let escape = false;
    let endIdx = -1;

    for (let i = arrayStart; i < text.length; i++) {
        const ch = text[i];
        if (escape) { escape = false; continue; }
        if (ch === '\\') { escape = true; continue; }
        if (ch === '"') { inString = !inString; continue; }
        if (inString) continue;
        if (ch === '[') depth++;
        else if (ch === ']') { depth--; if (depth === 0) { endIdx = i + 1; break; } }
    }

    if (endIdx === -1) {
        throw new Error('Could not find end of workingHours array');
    }

    const jsonText = text.slice(arrayStart, endIdx).replace(/\\"/g, '"').replace(/\\\\/g, '\\');
    const raw = JSON.parse(jsonText);

    return raw
        .filter(item => item.active === '1' || item.active === 1)
        .map(item => ({
            externalId: String(item.id),
            city: item.city,
            address: item.address,
            addressSlug: item.addressSlug ?? null,
            lat: item.lat ?? null,
            lng: item.lng ?? null,
            phones: item.phones ?? [],
            workTimes: item.workTimes ?? [],
        }));
}

export async function submitLocations(store, locations) {
    const response = await fetch('https://superakcijos.lt/api/scrapers/store-locations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ store, locations }),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${JSON.stringify(data)}`);
    }

    return data;
}
