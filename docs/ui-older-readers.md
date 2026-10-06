# UI for older readers (2026-10)

## Why

Most people reading leaflets on evaistine.lt are older. The interactive leaflet beta (large type, worded buttons, clear steps) was liked, and the owner asked for the same style across the whole site, with an app-like feel on phones. This replaced the earlier rule of matching the old Next.js site pixel for pixel.

## Rules

- **Text:** nothing under 16px. Page titles are 28px on phones and 32px from sm, section headings 24/28px. The Tailwind scale is shifted up one step in `resources/css/app.css` (`@theme`), so existing classes grew without editing templates:

  | Class | Size |
  |---|---|
  | `text-xs` | 16px |
  | `text-sm` | 17px |
  | `text-base` | 18px (body) |
  | `text-lg` | 20px |
  | `text-xl` | 24px |
  | `text-2xl` | 28px |
  | `text-3xl` | 32px |
  | `text-4xl` | 40px |

  Don't use `text-[Npx]`.
- **Controls:** 48px tall at least (`min-h-12` / `size-12`). Buttons carry a word next to their icon. The exceptions (owner's decision): the header's account, favourites and menu buttons, and every close button (`.sheet-close`, a plain X). Those are icon only, without a border, with `aria-label`.
- **Colors:**
  - **Filled buttons and active tabs:** `bg-action` (`#07843f`, white text 4.8:1), hover `bg-action-hover`.
  - **Text links:** `text-dark-green` (`#044923`). Plain `text-green` and white-on-`bg-green` are too low contrast (about 3:1).
  - **Greys:** `gray-400` and `gray-500` are darkened in the theme.
- **Focus:** a 3px outline on every link and control (base rule in `app.css`).
- **No surprises:** nothing jumps or changes on its own. Popups close with the phone's Back button. No popup opens by itself on a timer: the old 10-second signup popup became `<x-signup-inline-card>`, a card in the page flow (home page, product page).
- **Dates in words:** "iki spalio 5 d.", "rugsėjo 8–14 d.", never "Iki 10.05" or "2026.09.08 – 2026.09.14". A bare number pair reads as either day.month or month.day. The year is only added when it isn't this year. Use `LithuanianDate::dayMonthGenitive()` / `LithuanianDate::range()`, or `ProductPageMeta::validUntilLabel()` for offers.
- **No sideways strips on phones:** below sm, deal carousels show 4 cards two to a row plus a full-width "Žiūrėti visas (N)" button. Home stores and leaflets wrap two to a row, and categories are a one-column list with a "Rodyti visas" toggle. A cut-off card and tiny dots weren't read as "swipe for more". From sm up, the strips stay.
- **Full product names:** deal cards never cut a name with "…". A minimum height keeps short names level.
- **Where and until when, up top:** the product hero shows the price, "Įprasta kaina", then one row with the store logo and "Galioja iki …". The leaflet viewer has the same logo + dates row above it on phones. On phones the product photo is full width with the text under it.
- **Charts:** SVG text is in user units, so the price chart sets its viewBox width to the real rendered width (16px labels stay 16px). Points have a finger-sized hit area and respond to a tap as well as hover.

## Shared pieces

- **`site-header`:** one row, 56px on phones and 72px from lg (it used to be two rows, 144px). In order:
  - logo;
  - a categories icon that opens the categories sheet;
  - text links with counts in brackets, from lg ("Akcijos (17 366)", "Leidiniai (28)", "Parduotuvės (24)"), the active one underlined in green;
  - the search field (an icon below xl);
  - favourites and menu as icons.

  Account, "Didžiausios nuolaidos" and popular products are in the menu. The breadcrumbs stay visible on phones.
- **`mobile-bottom-nav`:** four worded tabs (Pradžia, Akcijos, Leidiniai, Stebimos), always visible.
- **`--header-h`** (CSS variable): 3.5rem on phones, 4.5rem from lg. Sticky bars dock at `top-[calc(var(--header-h)+env(safe-area-inset-top,0px))]`.
- **Store cards (`x-store-card`):** two buttons with the count declined Lithuanian-style ("5 leidiniai", "1 leidinys", "252 akcijos", "10 akcijų" via `LithuanianPlural::leafletWord` / `promotionWord`) instead of separate count lines.
- **Popups:** `.sheet-backdrop` / `.sheet-panel` / `.sheet-handle` / `.sheet-head` / `.sheet-close`. A bottom sheet on phones, a centered card from sm up. Add `x-back-closes="openExpression"` (Alpine directive in `resources/js/app.js`) so Back closes it.
- **Other classes:**
  - `.crumb-link`, `.crumb-current`, `.crumb-sep`: breadcrumbs;
  - `.section-link`: "Žiūrėti visas" style links;
  - `.deal-card-width`: card widths in flex-wrap listings, 2 / 2 / 3 / 4 per row.
- **Page transitions:** `@view-transition { navigation: auto; }`, with the header and the bottom nav kept in place (`view-transition-name`). Off when `prefers-reduced-motion` is set.
- **Leaflet page:** the interactive viewer (`leaflets/partials/beta*`) is on for every leaflet that has pages. The instruction, search and filters only show when the leaflet has clickable products.

## Audit

`storage/app/audit.cjs` (local only, not committed) loads a URL list at 390px and 1440px and counts:

- visible text under 16px;
- links and controls under 44px tall;
- horizontal overflow;
- JS errors.

Run it with `sail exec laravel.test node storage/app/audit.cjs shots`.

Result before deploy: 0 everywhere, except the Leaflet map widgets on store/city pages (third-party markers and attribution), the input inside the home page's search pill (its wrapper is 48px), and on `/kuponai/temu`, 2 controls under 44px that were not tracked down.
