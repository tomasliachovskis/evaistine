import { USER_AGENT } from '../_shared.js';

// www.nuolaidos.lt (a third-party store directory, not superakcijos) keeps
// addresses and hours for the big chains. Each /{slug}-darbo-laikas page
// embeds a complete workingHours JSON array for every location of that chain
// in the page's Next.js RSC flight-data script tag. A plain fetch() with a
// browser User-Agent gets the same HTML a browser does; Node's default UA is
// blocked.
export default async function nuolaidos({ slug }) {
    const response = await fetch(`https://www.nuolaidos.lt/${slug}-darbo-laikas`, {
        headers: { 'User-Agent': USER_AGENT },
        signal: AbortSignal.timeout(15000),
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const text = await response.text();

    // Embedded as a JS string literal inside the RSC flight chunk, so quotes
    // appear backslash-escaped in the raw HTML (\"workingHours\": rather
    // than "workingHours":). Search and bracket-match on that escaped form,
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

    return JSON.parse(jsonText)
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
