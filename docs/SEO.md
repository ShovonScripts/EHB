# SEO.md

## 1. Page Titles

Template: `{Content Title} | {site_name}` for detail pages; `{Section Name} | {site_name}` for listings; `{site_name} — {Tagline/Title}` for homepage. `site_name` is the admin-editable setting (seeded with the journalist's name). It is suppressed when the page title already contains it, so no page renders `About Emrul Hasan Bappi | Emrul Hasan Bappi`. Editable override per content item via `seo_title`.

## 2. Meta Descriptions

Auto-derived from `summary`/dek by default, editable override via `seo_description`. Kept 150–160 characters. For External Work, the description should describe *the fact of the work and where it was published*, not attempt to summarize the outlet's full article in a way that competes with it in search.

## 3. Canonical URLs

- **Internal Articles:** canonical = the article's own URL on this site (this site is the source of truth).
- **External Works:** canonical = **this site's own summary page URL** (e.g. `/work/{slug}`), *not* the external URL. The page is a distinct piece of content (the journalist's own description/citation of his work), so it should self-canonicalize rather than pointing at a page this site doesn't control. This avoids ambiguity while making clear via structured data (§6) that it references an external source.
- `canonical_url_override` field available per item for edge cases.

## 4. Open Graph

Every content item and key page emits `og:title`, `og:description`, `og:image` (dedicated OG image → featured image → site-wide default from Settings), `og:type` (`article` for content items, `website` elsewhere), `og:url`.

The share image is always passed whole — URL, alt text and stored dimensions together — so `og:image`, `og:image:alt` and `og:image:width`/`og:image:height` describe the same picture. The page's own values win when the page supplies the image; the default image's own values apply when it is the fallback. Mixing them (the default's alt on the page's image) is a defect, covered by `ShareCardTest`.

Article pages additionally emit `article:published_time`, `article:modified_time`, `article:section` (category) and one `article:tag` per tag.

## 5. Twitter/X Cards

`summary_large_image` card type using the same title/description/image as OG, for clean link previews when shared.

## 6. Structured Data

- **Person schema:** on `/about` and homepage — name, jobTitle, worksFor (Organization: publication name, generic), sameAs (social links), image.
- **Article schema:** on Internal Article detail pages — headline, datePublished, author (Person), image, articleBody reference.
- **CreativeWork / NewsArticle "citation" pattern for External Work:** rather than an `Article` schema claiming original authorship of content hosted elsewhere, use a lighter schema (e.g., `CreativeWork` with `isBasedOn`/`citation` pointing to the external URL, or simply omit `Article` schema and rely on OG/Twitter tags) — this avoids implying the site is the canonical publisher of syndicated work.
- **Organization schema:** only for the Publications themselves where relevant context is given (e.g., on `/publications/{slug}`), never asserting this site *is* that organization.

## 7. Breadcrumbs

`Home > [Section] > [Item]` on all detail and section pages, with matching `BreadcrumbList` structured data.

## 8. XML Sitemap

Auto-generated, including all Published content_items (both internal and external summary pages — internal because it's original content, external because the summary page is itself unique content), static pages, topic pages, and publication pages. Draft/Scheduled items excluded until published.

## 9. Robots.txt

Standard allow-all for public routes; disallow `/admin`, any preview/draft URLs, and internal search result query strings (`/search?q=`) to avoid thin-content indexing of arbitrary queries.

## 10. RSS/Atom

Recommended: yes — a single site-wide feed (`/feed`) and optionally per-section feeds (`/articles/feed`, `/investigations/feed`). Low cost to implement, valuable for readers, editors, and syndication partners tracking his output.

## 11. Image SEO

- Descriptive filenames and mandatory alt text (enforced in admin).
- Responsive `srcset` images served in modern formats (WebP) with fallbacks.
- Featured images included in sitemap image extension.

## 12. Internal Linking

- Related Content blocks and Topic pages are the primary internal-linking mechanism, concentrating link equity around subject-matter hubs.
- Every content item links back to its Category, Tags, and Topics.
- Publication pages link to all of that outlet's listed work, creating a natural cluster.

## 13. Archive Indexing Strategy

- `/work`, `/sections`, `/sections/{slug}`, `/topics` and `/topics/{slug}` are indexable — each is a genuinely distinct grouping with its own editorial description, and together they are the site's whole reason to exist.
- `/archive` and the per-type listing pages (`/articles`, `/investigations`, etc.) are indexable too, but the per-type pages list only on-site work, so they stay thin until he starts hosting pieces here.
- Filter permutations (`/archive?type=news&tag=…`, `/work?category=…&year=…`) are **not** indexed (`noindex,follow`) to avoid thin/duplicate bloat. This matters more than usual here because every filter state is a *duplicate* of a page that already exists: `/work?category=crime-justice` is the same collection as `/sections/crime-justice`, and `/work?topic=courts-trials` is the same as `/topics/courts-trials`. The canonical, indexable home for those collections is the section/beat page; the filter chips are navigation, not destinations.
- An empty section or beat 404s rather than rendering an indexable empty page.

## 14. Pagination

Paginated listing pages use sequential, crawlable pagination (`?page=2`, etc.) with `rel="next"`/`rel="prev"` link hints (or, if using "load more," ensure a crawlable paginated fallback exists) so deep archive content remains discoverable. With 200+ pieces this is the main discovery path, so the per-page size is 20 on `/work` and `/sections/{slug}` rather than 12.

**Out-of-range pages 404.** `?page=` accepts any integer, so without an explicit check `/work?page=12` on an 11-page archive answers 200 with an empty list — an unbounded set of indexable empty pages, each of which also blames the filters for being empty when no filter was applied. `Controller::abortIfPastLastPage()` enforces this on every paginated listing. Only `page > 1` with nothing on it is out of range: page 1 of a filter combination that genuinely matches nothing is a real empty state and stays a 200.

## 15. External Article SEO Behavior — Avoiding Duplicate Content

This is the most important SEO consideration given the internal/external model:

- The site **never reproduces the external article's full text** — only a journalist-written summary — so there is no body-content duplication risk.
- The External Work summary page **self-canonicalizes** (§3) rather than canonicalizing to the external URL, because the summary page's content (the journalist's own framing/description) is unique.
- Meta description and structured data for External Work pages are written to read as *"here is a piece I published at The Daily Star, click through to read it"* rather than attempting to restate the article's substance at length — keeping the page thin-but-legitimate rather than thin-and-duplicative.
- Outbound links to the external URL use `rel="noopener"` (and may use `rel="ugc"`-style attributes are not needed since this is the journalist's own verified citation, not user-generated content).
