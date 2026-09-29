# FRONTEND.md

## 1. Route Map

```
/                     Homepage
/about                Biography, career, education, awards
/work                 Reporting archive — everything published in an outlet
/work/{slug}          Single work summary + outbound link (canonical for outlet work)
/sections             The outlet's filing sections, with counts
/sections/{slug}      One section's pieces, filterable by beat
/topics               Beat dossiers, with counts
/topics/{slug}        Topic dossier page (cross-section, mixed content types)
/articles             On-site reporting (content_type=news, hosted here)
/articles/{slug}      Single news article hosted here
/investigations       On-site investigations
/investigations/{slug}
/interviews           On-site interviews
/interviews/{slug}
/opinions             On-site opinion/analysis
/opinions/{slug}
/multimedia           Video / photo story archive
/multimedia/{slug}
/publications         List of outlets he's written for
/publications/{slug}  All work tied to one publication
/archive              Full chronological/filterable archive, all types
/search               Site-wide search
/contact              Contact form
```

## 1a. The two browse axes

There are two orthogonal ways to slice the work, and the whole IA exists to
keep them distinct:

| Axis | Question it answers | Model | Browsable at |
|---|---|---|---|
| **Section** | Where did the outlet *file* this? | `categories` | `/sections/{slug}` |
| **Beat** | What is this *about*? | `topics` | `/topics/{slug}` |

A section is the outlet's own filing decision and its names are kept verbatim
so a reader who knows the paper can navigate by the same words. A beat is
editorial: the July uprising cases were filed under "Crime & Justice" one week
and "Bangladesh" the next, but they are one story, and a reader who wants all
of them needs the second axis. Both are seeded from
`database/seeders/data/{sections,beats}.php`.

The content_type pages (`/articles`, `/investigations`, …) are a **third,
separate** thing: what kind of piece it is. They list only work **hosted on
this site**. They deliberately do not list outlet work — a section page that
listed all 210 external pieces would be an index of links that each 301 to
`/work/{slug}`, which is a worse version of `/work` with extra steps. The
outlet archive has its own front door at `/work`.

**Consequence for the nav:** while everything lives at the outlet, the
content_type pages are empty, so they are hidden rather than shipped as four
dead links. `App\Support\HostedSections` counts published internal items and
the layout renders them only when that count is non-zero.

## 2. Page-by-Page Detail

### `/` Homepage
- **Purpose:** First impression — establish identity and credibility fast, then route visitors deeper.
- **Sections:** Hero (name, title, current role, portrait), **The Archive at a glance** (piece count, filing-since date, section count, beat count, plus the six largest of each as links), 3–5 featured/pinned works, "Latest Reporting" feed, "As Seen In" strip of publication logos, Investigations spotlight, short About teaser, Contact CTA.
- **Rationale for the archive band:** with the body of work living at an outlet, a homepage that only teases the latest six pieces undersells it. The band states the shape of the archive (210 pieces, 8 sections, 12 beats) and gives the two axis entry points in the first scroll.
- **Components:** Hero, ArchiveOverview, FeaturedWorkGrid, LatestFeed, PublicationLogoStrip, AboutTeaser, ContactCTA.
- **Required data:** journalist_profile, counts + top sections/topics, featured content_items, latest content_items, publications list.
- **SEO:** Person structured data, strong title/meta description, OG image of the journalist.

### `/about`
- **Purpose:** Establish credibility and depth of career.
- **Sections:** Long bio, career timeline, education, awards grid, skills/beats, social links.
- **Components:** BioBlock, TimelineList, AwardsGrid, SocialLinks.
- **Required data:** journalist_profile, career_history, education, awards.
- **SEO:** Person structured data with `worksFor` (Organization: The Daily Star, generic — not implying ownership).

### `/work` — the reporting archive
- **Purpose:** The site's primary browse surface. Every piece published in an outlet, newest first.
- **Sections:** Intro with live piece count and date span (derived from the data, never hardcoded), three composing filter rows — **Section**, **Beat**, **Year** — then a dense list of pieces, each row linking to its summary page and onward to the original.
- **Filter chips compose:** every chip is the current filter set with one axis swapped, so choosing a section then a beat does not silently drop the section. Chip URLs are built in the controller, not the view.
- **Only populated terms are offered.** A category/beat with no published pieces produces no chip, because every chip is a link and a link to an empty list is a dead end.
- **Components:** FilterChips, DenseContentList (via `<x-content-row>`), Pagination.
- **Required data:** published external content_items; sections, beats and years with counts.
- **SEO:** `/work` itself is indexable. Filtered permutations (`?category=…`) are the same collection as `/sections/{slug}` and `/topics/{slug}`; those canonical pages carry the indexable value and the permutations are `noindex,follow` (see `SEO.md`).

### `/sections` and `/sections/{slug}`
- **Purpose:** Browse by the outlet's own filing section.
- **Index:** card per section with description, piece count and latest date. Sections with nothing published are omitted.
- **Show:** section intro, piece count, filing date span, the beats that run through that section as filter chips, then the pieces.
- **404** for a section with no published pieces rather than an empty page.
- **Required data:** categories with published counts; per-section topic counts and date span (one grouped query, not one sub-select per section).

### `/topics` and `/topics/{slug}`
- **Purpose:** Browse by editorial beat, across sections.
- **Index:** card per dossier with description, piece count, latest date. Ranked widest-first so the grid reads as a list. Dossiers with nothing published are omitted.
- **Show:** dossier intro, chronological list across all sections and content types.
- **SEO:** Strong candidate for rich meta descriptions and internal linking hub value.

### `/articles`, `/investigations`, `/interviews`, `/opinions`, `/multimedia` (list pages)
- **Purpose:** Browse work **hosted on this site** by content type.
- **Scoped to `source_type = internal`.** Outlet work is reachable at `/work` and `/sections/{slug}` instead; listing it here too produced duplicate pages whose every link 301'd.
- **Sections:** Category filter (only categories that have internal published items of this type), paginated list, newest first.
- **Components:** FilterBar, ContentCard, Pagination.
- **SEO:** Paginated `rel=next/prev` or a canonicalized "load more"; filter states indexable only where they add unique value (see `SEO.md`).

### `/{type}/{slug}` (detail pages)
- **Purpose:** Present a single piece of work.
- **Internal variant sections:** Title, dek/summary, byline/date, full body, featured image, tags/topics, related content, share links.
- **External variant sections:** Title, dek/summary, byline/date, publication badge + logo, external URL, "Read Original Article →" prominent CTA, tags/topics, related content.
- **Components:** ArticleHeader, ArticleBody (internal only), ExternalCallout (external only), TagList, RelatedContentGrid.
- **Required data:** the content_item, its publication (if external), related items.
- **SEO:** Article structured data (internal) with full content; for external, structured data describes it as a reference/citation to avoid implying original authorship duplication (see `SEO.md`).

### `/publications`
- **Purpose:** Show the range of outlets he's contributed to.
- **Sections:** Grid of publication logos/names, each linking to a filtered view of his work at that outlet.
- **Components:** PublicationGrid.

### `/publications/{slug}`
- **Purpose:** All work (internal cross-posted mentions + external) tied to one outlet.
- **Sections:** Publication header (logo, name, link to outlet), filtered content list.

### `/topics/{slug}`
- **Purpose:** Editorial dossier on a subject, mixing content types and sources.
- **Sections:** Topic intro (optional description/image), chronological or curated list of related content_items across all types.
- **SEO:** Strong candidate for rich meta descriptions and internal linking hub value.

### `/archive`
- **Purpose:** The complete, powerful filterable index — the "everything" view for power users, researchers, award committees.
- **Sections:** Advanced filter bar (type, source, category, tag, topic, publication, year), dense list view, pagination.
- **Filter options are restricted to populated terms** — a category, tag, beat or outlet with no published pieces is left out of the dropdown, so the form cannot be submitted into an empty result set.
- **Components:** AdvancedFilterBar, DenseContentList (via `<x-content-row>`).

### `/search`
- **Purpose:** Site-wide search across all content_items (and optionally profile/about content).
- **Sections:** Search input, result list with type badges, empty-state guidance.
- **Required data:** DB full-text search results (see `SEO.md`/`DATABASE.md` for search approach); V1 uses DB LIKE/full-text, no external search service required.

### `/contact`
- **Purpose:** Let readers/sources/collaborators reach the journalist.
- **Sections:** Contact form (name, email, subject, message), optional direct email display, social links.
- **Components:** ContactForm.
- **Interaction:** Submits to `contacts` table; journalist reviews in admin Messages.
- **Security note:** honeypot + rate limiting on submission (see `SECURITY.md`).

## 3. Shared Components

- **Header/Nav:** name, primary nav (Reporting, Sections, Beats, on-site content_type sections when populated, Archive, Publications, About, Contact), search icon. The nav list is assembled once in the layout and shared by the desktop and mobile menus so the two cannot drift.
- **Footer:** short bio blurb, social links, publication logos, a "Browse" link column (Reporting, Sections, Beats, Archive, Publications), copyright, and the "not an official publication of The Daily Star" disclaimer.
- **ContentCard:** title, dek, date, type badge, source badge (internal vs. publication name), thumbnail. Used for on-site work and the homepage.
- **ContentRow:** the dense one-line-per-piece row (date, section, headline + dek, outbound source) shared by `/work`, `/archive` and `/sections/{slug}`, so three listings of the same collection read identically. The outlet pieces have no images, so a card grid would waste a third of the screen on empty boxes.
- **Breadcrumbs:** on all detail and section pages, feeding structured data.

## 4. Homepage Detail (per spec)

1. Journalist identity — name, title, portrait in hero.
2. Current role — "Currently reporting for The Daily Star" style line, phrased as his role, not the site being Daily Star's.
3. The archive at a glance — piece count, filing-since, section count, beat count, and the six largest of each as links into `/sections` and `/topics`.
4. Featured work — curated grid (manually marked `is_featured`).
5. Latest reporting — auto feed of most recent published items.
6. Selected external publications — logo strip / mini list linking to `/work` or `/publications`.
7. Investigations — spotlight block, 2–3 pinned investigations.
8. About/profile — short teaser + link to `/about`.
9. Contact — CTA block linking to `/contact`.
