// Helpers shared by the pharmacy location sources in ./sources/. Every source
// returns locations in one shape, which POSTs to /api/scrapers/store-locations:
//   { externalId, city, address, addressSlug, lat, lng, phones, workTimes }
// workTimes uses nuolaidos.lt's notation, which App\Support\WorkingHoursParser
// reads: ["I-V 08:00-20:00", "VI 09:00-15:00", "VII Nedirba"]. A day left
// out is "unknown" (no hours shown); "Nedirba" shows it as closed.

const API_URL = process.env.SCRAPER_API_URL || 'http://localhost/api/scrapers';

export const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

export async function fetchHtml(url) {
    const response = await fetch(url, {
        headers: { 'User-Agent': USER_AGENT, 'Accept-Language': 'lt-LT,lt;q=0.9' },
        signal: AbortSignal.timeout(30000),
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status} for ${url}`);
    }

    return response.text();
}

// Parses the JSON array/object literal that starts right after `marker` in a
// page's inline <script> (e.g. "var deliveryStores=").
export function jsonAfter(text, marker) {
    const at = text.indexOf(marker);
    if (at === -1) {
        return null;
    }

    return JSON.parse(literalAt(text, at + marker.length));
}

// The balanced [...] or {...} literal starting at `start`, as text. Strings
// are skipped, so brackets inside them don't count.
export function literalAt(text, start) {
    const open = text[start];
    const close = open === '[' ? ']' : '}';
    let depth = 0;
    let inString = false;
    let escape = false;

    for (let i = start; i < text.length; i++) {
        const ch = text[i];
        if (escape) { escape = false; continue; }
        if (ch === '\\') { escape = true; continue; }
        if (ch === '"') { inString = !inString; continue; }
        if (inString) continue;
        if (ch === open) depth++;
        else if (ch === close && --depth === 0) {
            return text.slice(start, i + 1);
        }
    }

    throw new Error(`Unterminated literal at ${start}`);
}

export const slugify = (text) => String(text)
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');

export const cleanText = (text) => String(text ?? '')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;| /g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

// "8" -> "08:00", "8.30" / "8:30" -> "08:30".
const toTime = (value) => {
    const [h, m = '00'] = value.split(/[.:]/);
    return `${h.padStart(2, '0')}:${m.padEnd(2, '0')}`;
};

const RANGE = /(\d{1,2}(?:[.:]\d{2})?)\s*[-–—]\s*(\d{1,2}(?:[.:]\d{2})?)/g;

// One day's hours text -> "08:00-20:00", "09:00-13:00, 14:00-16:00" (lunch
// break), "Nedirba", or null when there's no time in it.
export function normalizeHours(text) {
    const value = cleanText(text);

    if (/nedirb|u[žz]daryt|nedirbame/i.test(value)) {
        return 'Nedirba';
    }

    // "7.30-15.30 (pietūs 11.30-12.00)": open range with a lunch break.
    const lunch = value.match(/piet[ūu]s?\s*(?:pertrauka)?:?\s*(\d{1,2}(?:[.:]\d{2})?)\s*[-–—]\s*(\d{1,2}(?:[.:]\d{2})?)/i);
    const ranges = [...value.replace(lunch?.[0] ?? '', '').matchAll(RANGE)].map(m => [toTime(m[1]), toTime(m[2])]);

    if (ranges.length === 0) {
        return null;
    }

    if (lunch && ranges.length === 1) {
        const [open, close] = ranges[0];
        return `${open}-${toTime(lunch[1])}, ${toTime(lunch[2])}-${close}`;
    }

    return ranges.map(([open, close]) => `${open}-${close}`).join(', ');
}

const DAY = '(?:VII|VI|IV|V|III|II|I)';
const DAYS = new RegExp(`^${DAY}(?:\\s*[-–]\\s*${DAY})?$`);

// [["I - V", "8.00 - 16.00"], ["VI", "Nedirba"]] -> ["I-V 08:00-16:00", "VI Nedirba"]
export function workTimesFromRows(rows) {
    const workTimes = [];

    for (const [days, hours] of rows) {
        const dayText = cleanText(days).replace(/\s*[-–]\s*/g, '-');
        const time = normalizeHours(hours);

        if (DAYS.test(dayText) && time) {
            workTimes.push(`${dayText} ${time}`);
        }
    }

    return workTimes;
}

// "I-V  8 - 19 VI  8 - 16 VII  10 - 15" -> rows for workTimesFromRows().
export function rowsFromLine(line) {
    const text = cleanText(line);
    const marker = new RegExp(`(?:^|\\s)(${DAY}(?:\\s*[-–]\\s*${DAY})?)(?=\\s+\\d|\\s+ned|\\s+u[žz])`, 'gi');
    const found = [...text.matchAll(marker)];

    return found.map((m, i) => [
        m[1],
        text.slice(m.index + m[0].length, found[i + 1]?.index ?? text.length),
    ]);
}

const ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII'];

// Per-weekday hours (Monday first; each "08:00-20:00", "Nedirba", or null
// for unknown) -> grouped workTimes: ["I-V 08:00-20:00", "VI-VII Nedirba"].
export function workTimesFromWeek(days) {
    const workTimes = [];
    let start = 0;

    for (let i = 1; i <= 7; i++) {
        if (i < 7 && days[i] === days[start]) {
            continue;
        }
        if (days[start]) {
            const range = start === i - 1 ? ROMAN[start] : `${ROMAN[start]}-${ROMAN[i - 1]}`;
            workTimes.push(`${range} ${days[start]}`);
        }
        start = i;
    }

    return workTimes;
}

// One day as "08:00-20:00" from an open and a close time ("8:00", "08:00:00");
// "Nedirba" when both are missing, "-", or equal (00:00-00:00).
export function dayHours(open, close) {
    const clean = (t) => {
        const match = String(t ?? '').trim().match(/^(\d{1,2})[:.](\d{2})/);
        return match ? `${match[1].padStart(2, '0')}:${match[2]}` : null;
    };
    const from = clean(open);
    const to = clean(close);

    return from && to && from !== to ? `${from}-${to}` : 'Nedirba';
}

// "GINKŪNŲ K." -> "Ginkūnų k.", "NAUJOJI AKMENĖ" -> "Naujoji Akmenė".
export const titleCaseTown = (text) => cleanText(text)
    .toLocaleLowerCase('lt')
    .split(' ')
    .map(word => (/^(k|km|sen|m|vs|kaim|r|raj|sav)\.$/.test(word) ? word : word.charAt(0).toLocaleUpperCase('lt') + word.slice(1)))
    .join(' ');

// Municipality (genitive, as in "Šilalės r. sav.") -> its centre town, for
// addresses that name only the municipality.
const MUNICIPALITY_CENTRES = {
    'Akmenės': 'Naujoji Akmenė', 'Alytaus': 'Alytus', 'Anykščių': 'Anykščiai', 'Birštono': 'Birštonas',
    'Biržų': 'Biržai', 'Druskininkų': 'Druskininkai', 'Elektrėnų': 'Elektrėnai', 'Ignalinos': 'Ignalina',
    'Jonavos': 'Jonava', 'Joniškio': 'Joniškis', 'Jurbarko': 'Jurbarkas', 'Kaišiadorių': 'Kaišiadorys',
    'Kalvarijos': 'Kalvarija', 'Kauno': 'Kaunas', 'Kazlų Rūdos': 'Kazlų Rūda', 'Kėdainių': 'Kėdainiai',
    'Kelmės': 'Kelmė', 'Klaipėdos': 'Klaipėda', 'Kretingos': 'Kretinga', 'Kupiškio': 'Kupiškis',
    'Lazdijų': 'Lazdijai', 'Marijampolės': 'Marijampolė', 'Mažeikių': 'Mažeikiai', 'Molėtų': 'Molėtai',
    'Neringos': 'Neringa', 'Pagėgių': 'Pagėgiai', 'Pakruojo': 'Pakruojis', 'Palangos': 'Palanga',
    'Panevėžio': 'Panevėžys', 'Pasvalio': 'Pasvalys', 'Plungės': 'Plungė', 'Prienų': 'Prienai',
    'Radviliškio': 'Radviliškis', 'Raseinių': 'Raseiniai', 'Rietavo': 'Rietavas', 'Rokiškio': 'Rokiškis',
    'Skuodo': 'Skuodas', 'Šakių': 'Šakiai', 'Šalčininkų': 'Šalčininkai', 'Šiaulių': 'Šiauliai',
    'Šilalės': 'Šilalė', 'Šilutės': 'Šilutė', 'Širvintų': 'Širvintos', 'Švenčionių': 'Švenčionys',
    'Tauragės': 'Tauragė', 'Telšių': 'Telšiai', 'Trakų': 'Trakai', 'Ukmergės': 'Ukmergė',
    'Utenos': 'Utena', 'Varėnos': 'Varėna', 'Vilkaviškio': 'Vilkaviškis', 'Vilniaus': 'Vilnius',
    'Visagino': 'Visaginas', 'Zarasų': 'Zarasai',
};

// True for an address part that names a municipality/county, not a town.
export const isRegionPart = (part) => /(sav\.|aps\.|apsk\.|raj\.|rajonas|\sr\.|\ssen\.)\s*$/i.test(part.trim());

export function municipalityCentre(part) {
    const name = part.replace(/\s*(r\.\s*sav\.|m\.\s*sav\.|sav\.|raj\.|rajonas|r\.|apsk\.)\s*$/i, '').trim();
    return MUNICIPALITY_CENTRES[name] ?? null;
}

export const phoneFrom = (text) => {
    const match = cleanText(text).match(/\+?\d[\d\s()-]{6,}\d/);
    return match ? match[0].replace(/\s+/g, ' ').trim() : null;
};

export async function submitLocations(store, source, locations) {
    const response = await fetch(`${API_URL}/store-locations`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ store, source, locations }),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${JSON.stringify(data)}`);
    }

    return data;
}
