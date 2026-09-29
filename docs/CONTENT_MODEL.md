# CONTENT_MODEL.md

## 1. Core Architectural Decision: Unified Content, Typed by Kind

Rather than building separate first-class systems for "Articles," "Investigations," "Interviews," and "Opinions" (which would duplicate fields like title, slug, publish date, SEO, media, tags, categories four-plus times), the model uses **one `content_items` concept with a `content_type` classifier**, plus a **`source_type` (internal/external) flag**.

```
ContentItem
├── source_type: internal | external
├── content_type: news | investigation | interview | opinion | video | photo_story | other
├── shared fields (title, slug, summary, dates, media, SEO, tags, categories, topics)
├── internal-only fields (body, reading_time)
└── external-only fields (publication, external_url, byline_note)
```

This is explained further in `DATABASE.md` §2 (Option A vs Option B). The recommendation is a **single table with a discriminator**, not per-type tables — see reasoning there.

## 2. Internal Article

**Purpose:** A piece of journalism fully hosted and readable on the journalist's own website.

**Required fields:** title, slug, content_type, summary/dek, body (rich text), published_date, status.

**Optional fields:** featured_image, gallery/media attachments, categories, tags, topics, related_content, byline_note (e.g. co-reporting credit), reading_time, SEO overrides.

**Relationships:** belongs to zero-or-one Category (or many, if multi-category is desired — recommend single primary category + free tags), many-to-many Tags, many-to-many Topics, many-to-many related ContentItems, has-many Media.

**Publishing behavior:** Draft → Scheduled → Published. Published items appear in relevant archive/listing pages and sitemap.

**URL behavior:** Canonical, on-site URL, e.g. `/articles/{slug}` (or `/investigations/{slug}`, `/interviews/{slug}`, `/opinions/{slug}` depending on content_type — see `FRONTEND.md` for routing).

## 3. External Work

**Purpose:** Represents a piece of journalism published elsewhere (The Daily Star or another outlet); the site stores metadata and sends the visitor to the original.

**Required fields:** title, slug (for the internal representation page), content_type, summary/description, published_date, publication (FK), external_url, status.

**Optional fields:** publication_logo_override, featured_image, categories, tags, topics, article_type note, related_content.

**Relationships:** belongs to one Publication (required), many-to-many Tags/Topics, optional Category.

**Publishing behavior:** Same Draft/Scheduled/Published lifecycle as internal content, controlling only its *listing* visibility on this site (the original article's own publication status is independent and external).

**URL behavior:** The site renders a metadata/summary page at its own URL (e.g. `/work/{slug}`) containing a prominent "Read Original Article →" link to `external_url`. The full external body is never scraped or reproduced — only the summary the journalist writes himself.

## 4. Content Types (content_type values)

| Type | Notes |
|---|---|
| News Article | Standard reporting; internal or external. |
| Investigation | Longer-form investigative work; often internal-first; may cross-list an external syndication. |
| Interview | Q&A or profile-style interview conducted by the journalist. |
| Opinion / Analysis | First-person opinion or analytical commentary. |
| Video | Multimedia; body may be minimal, primary asset is a video embed/file. |
| Photo Story | Multimedia; primary asset is a gallery. |
| Other Work | Catch-all for professional output that doesn't fit the above (e.g., panel talk write-up, research contribution). |

No separate database tables are created per type; `content_type` plus conditional UI (in the admin) handles type-specific field emphasis (e.g., Video shows a video-URL field; Interview shows an "interviewee" field).

### Type-specific optional fields (stored as nullable columns or a JSON `meta` field)
- Interview: `interviewee_name`, `interviewee_title`.
- Video: `video_url` or `video_embed`.
- Photo Story: gallery media collection.
- Investigation: `investigation_status` (ongoing/concluded) — optional, only if the journalist wants it.

## 5. Categories — the outlet's filing sections

A small, curated, mostly-static taxonomy. One primary category per content item, to keep navigation clean.

**In practice these are the sections the outlet files bylines under**, kept verbatim so a reader who knows the paper can navigate by the same words: Crime & Justice, Bangladesh, News, Accidents & Fires, Politics, Transport, Health, Culture. Seeded from `database/seeders/data/sections.php`; browsable at `/sections/{slug}`.

They are the *filing* axis, not the subject axis. See §7 for that.

## 6. Tags

Free-form, journalist-managed, many-to-many. Used for fine-grained discovery (e.g., "Rohingya," "Election 2026," "RMG sector").

## 7. Topics — the beat dossiers

A curated, higher-level grouping distinct from tags — intended to power `/topics/{slug}` landing pages that read like a mini dossier on a subject, potentially mixing internal and external work. Topics are editorially chosen, not auto-generated from tags, though a topic may be *seeded* from a tag.

**Topics are the subject axis, and they are what carry the site's navigation once most work lives at an outlet.** The two are not redundant:

- A **category** answers *where was this filed*. A July uprising case was filed under "Crime & Justice" one week and "Bangladesh" the next, because the outlet refiled it.
- A **topic** answers *what is this about*. All the July uprising cases are one story, and a reader who wants all of them needs the second axis.

So a piece carries one category and one-to-many topics. In practice the beats are the through-lines of an archive rather than a partition of it: "Courts & Trials" and "Victims & the Long Wait" overlap heavily and both cut across sections, which is the point — the reader arrives by subject and then sees the filing sections it spans.

The current 12 beats are seeded from `database/seeders/data/beats.php`, and per-article assignments live in `database/seeders/data/published_work.php`. Both are plain data files rather than seed logic so a classification can be reviewed and changed without reading any PHP. Admin → Content → Topics makes the same change without a redeploy.

## 8. Publications

**Purpose:** Generic registry of any outlet the journalist's work has appeared in.

**Fields:** name, slug, logo (optional), website_url, description (optional).

**Not hard-coded:** The Daily Star is simply a row in this table like any other; the admin can add new publications freely.

## 9. Media

Represents uploaded images/video/audio/documents. A single media library, attachable to any ContentItem (featured image, gallery, inline body assets) and to Profile/Awards/Publications (logos).

## 10. Documents

Where a professional publication (e.g., a PDF report, whitepaper, book excerpt) needs to be downloadable rather than just linked, treat it as a Media item of type `document` rather than a separate table.

## 11. Related Content

A simple many-to-many self-relation on ContentItem (`related_content_id`), manually curated by the journalist in the admin. Auto-suggestion (by shared tags/topics) can be layered on top later as a "you may also like" fallback when no manual relations exist — no separate table required for that fallback logic.

## 12. Summary of What Was Deliberately Not Modeled Separately

Investigations, Interviews, Opinions, and News are **not** separate tables — they are `content_type` values on one ContentItem model. This avoids four near-identical schemas, four near-identical admin resources, and four near-identical query/search paths, while still allowing type-specific display and type-specific optional fields.
