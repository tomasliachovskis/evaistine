---
name: seo-content
description: Playbook for generating or revising GPT-written SEO copy for store/category/leidinys-style pages on this site (App\Services\DescriptionGenerationService and siblings). Use before writing a new generation prompt, adding a new page type's SEO copy, or debugging why generated copy reads generic/padded/wrong-intent.
---

# SEO content generation playbook

Distilled from the `/leidinys/{store}` content project — several real correction cycles (too thin → too padded → wrong intent → stale data baked into evergreen copy → headings that didn't match the real reference page → sections too thin) are captured here so they don't have to be rediscovered.

## 1. Reuse the existing infra, don't reinvent it

- `App\Services\DescriptionGenerationService` is the established pattern: raw `Http` facade calls to `https://api.openai.com/v1/chat/completions` (no SDK), never Gemini for text generation (Gemini is reserved for the flyer-OCR/vision pipeline).
- `config('services.openai.model', 'gpt-5-mini')` is the cheap default used everywhere else in the app (category mapping, FAQs, news articles). For a new long-form, SEO-load-bearing page (where quality matters more than the marginal cost), add a dedicated override in `config/services.php` (see `services.openai.model_leaflet` → `gpt-5`) rather than bumping the shared default and silently changing cost/behavior for every other call site.
- Drive generation via a synchronous Artisan command (`App\Console\Commands\GenerateDescriptions`, pattern: `descriptions:generate {type} --id=|--all`), not a queued job — these are editorial, human-reviewed runs, not hot-path work.
- Store the generated HTML on the model (a dedicated column, e.g. `Store::$leaflet_description`), not regenerated per page load. Keep a template-based fallback for rows that haven't been generated yet so nothing regresses mid-rollout.
- Ground copy in real research via a `storage/app/*_semantic_research.json` file keyed by slug (see `store_semantic_research.json`: `business_type`, `distinctive_angle`, `real_search_phrases`, `history_facts`, `notable_categories_or_products`). A new page type doing similar linking/facts work should read from the *same* file rather than duplicating it, only adding a page-type-specific file (e.g. `store_leaflet_semantic_research.json`) for genuinely different keyword intent.

## 2. Research the real reference page FIRST — don't invent structure

Before writing the system prompt, fetch the actual live competitor/reference page that ranks for the target search intent (WebFetch or browser) and write down its **exact heading text**, section order, and rough word count. Then mirror that pattern as literally as possible in the prompt — give the model fixed heading templates ("heading N must be exactly '[X]: viskas, ką reikia žinoti', only the store name changes"), not "here's an example, improvise something similar." This was the single most repeated correction in the leidinys project: headings that were merely heading-*shaped* (a colon-phrase or a question) kept drifting away from what the reference page actually says, because the prompt left the exact wording to the model's discretion.

## 3. One search intent per page — never blend adjacent intents

If the site has separate pages for adjacent topics (e.g. "discounts at this store" vs. "this store's catalog"), each page's copy must stay confined to its own subject, even where the adjacent topic feels naturally helpful to mention (e.g. savings tips, loyalty-card advice on a catalog page). Cross-*link* to the other page instead of duplicating or leaking its subject matter in. Before finalizing a prompt, state explicitly in it what the page is NOT about, with a self-check instruction ("if a sentence could just as easily belong on the other page, rewrite it").

## 4. Evergreen-content discipline

- Never print an exact stat that will be stale before the content is next regenerated: round/qualify discount percentages, counts, and validity dates.
- Strip ephemeral identifiers (issue/edition numbers, "Nr. 37") **before** the data ever reaches the LLM — dedupe by normalized name, not raw title. Bug found this session: a weekly-numbered catalog series ("AČIŪ savaitinis leidinys Nr. 37/36/34") needed collapsing into one evergreen name ("AČIŪ savaitinis leidinys") in the PHP data-building step, plus an explicit output-side ban, because the raw titles alone let the model echo a specific issue number into "evergreen" copy.
- Exception: **permanent** historical facts (founding year, brand-launch year, ownership) are not stale-prone — state them exactly ("1992 metais"), don't vague them into "in the 1990s."

## 5. Density over length — ban filler explicitly

State a target word count in the prompt and actually verify it (`str_word_count(strip_tags($html))` in tinker after generating — don't just trust the prompt's instruction). Explicitly instruct the model to cut:
- sentences that only restate their own heading in different words,
- generic instructions the reader obviously already knows (e.g. "check the cover for the date," "plan your shopping ahead"),
- transition/filler sentences that carry no new fact.

A short, fact-dense paragraph is correct; padding to hit a length target is not.

## 6. Ground every claim in real data — nothing invented

The semantic-research JSON files are the anti-generic mechanism. Before asking the model to write about a new entity, make sure its file entry actually has real researched facts (web search / verified public sources), and tell the prompt explicitly which fields are safe to state as hard facts (history/founding data) vs. must stay qualitative (live stats). If the research file lacks a field for a given entity, the prompt must fall back to whatever real facts *are* available rather than writing generic filler — never invent a fact not present in the data.

## 7. Internal linking conventions

Embed contextual `<a href>` links with natural anchor text, sourced only from a link list built in PHP from real data (category listings, keyword pages, a store's own contacts/locations page) — never let the model invent a URL. Specify the exact anchor-text convention when it matters (e.g. `"[store_name] parduotuvės ir kontaktai"` for a store's locations page) rather than leaving phrasing to the model.

## 8. Site-wide style rules

- No `<strong>/<b>/<em>/<i>` anywhere in generated copy — bolded phrases read as AI-generated. Links (`<a>`) are the only inline markup.
- No emoji anywhere (repo-wide rule, not SEO-specific).
- Lithuanian grammar: never the construction "Pas [Store]" (e.g. "Pas Rimi rasite...") — decline the store name properly instead.
- Reuse existing CSS utility classes for headings/sections (e.g. `.section-heading`) instead of inventing bespoke Tailwind strings, and note that a raw-HTML-rendering wrapper needs `!`-forced overrides (`[&_h2]:text-lg!`) if it must guarantee a size regardless of what class the generated HTML itself carries.

## 9. Test one, then batch

Generate for a single representative entity first, actually read the output (word count, structure, tone, intent-correctness), only then loop the rest. After *any* prompt change, re-test on that same entity before re-batching — don't batch-regenerate on a hunch that a prompt edit worked.

## 10. Verifying in the browser

- `sail artisan cache:clear-discounts` after any content/column change — the relevant caches are versioned (bumped by this command), not just TTL-based, so stale content can persist even after the DB is updated.
- If any Tailwind class changed, rebuild frontend assets with `sail exec laravel.test npm run build` — this host's native `npm run build` is broken (missing `@rolldown/binding-darwin-x64`; only the Linux binding is installed), so it must run inside Sail.
- Always look at the actual rendered page after clearing cache and rebuilding, not just the raw generated HTML string — spacing, heading size, and link styling bugs only show up visually.
