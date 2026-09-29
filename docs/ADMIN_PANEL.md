# ADMIN_PANEL.md

Planned implementation: **Laravel + Filament** (no Filament code written here — structure/behavior only).

## 1. Admin Navigation

```
Dashboard

Content
├── All Content (unified table view, filterable by type/status/source)
├── News Articles
├── Investigations
├── Interviews
├── Opinions / Analysis
├── Multimedia (Video / Photo Story)
├── Categories
├── Tags
├── Topics
└── Publications

Journalist
├── Profile
├── Career History
├── Education
└── Awards

Media
└── Media Library

Messages (Contact submissions)

SEO
├── Redirects
└── Global SEO Defaults

Settings
```

Note: "News Articles / Investigations / Interviews / Opinions / Multimedia" are **filtered views of the same `content_items` resource** (scoped by `content_type`), not separate Filament resources with separate tables — consistent with `DATABASE.md` §1. This keeps one form builder with conditional fields, not five near-duplicate resources.

## 2. Dashboard Widgets

- Content counts by status (Draft / Scheduled / Published).
- Recently published items (quick links to edit).
- Upcoming scheduled items.
- Unread contact messages count.
- Quick "New Content" action (opens type-aware create form).

## 3. CRUD Functionality

- **Content Items:** Create/edit form adapts fields by `content_type` and `source_type`:
  - Selecting `source_type: internal` reveals the rich-text body editor, reading time.
  - Selecting `source_type: external` reveals Publication selector, External URL, hides body editor.
  - Selecting `content_type: interview` reveals interviewee fields; `video` reveals video URL/embed; `photo_story` reveals gallery uploader.
  - Shared fields (title, slug, summary, featured image, category, tags, topics, related content, SEO block, featured toggle, status, published date) always present.
- **Categories / Tags / Topics / Publications:** simple CRUD tables with name/slug (+ logo for Publications, + description/image for Topics).
- **Journalist Profile:** single-record edit form (not a list) since V1 is single-journalist. Implemented as an edit-only resource with no create route — see §9.
- **Career / Education / Awards:** repeatable, sortable CRUD lists attached to the profile.
- **Media Library:** upload, tag with alt text/caption, browse/search, reuse across content items.

## 4. Publishing Controls

- Status field: Draft, Scheduled, Published — journalist sets this directly, no approval gate in V1.
- Scheduled items carry a `published_at` in the future; a scheduled job flips status to Published at that time (or the query layer simply treats `published_at <= now()` as the visibility check, avoiding a cron dependency — recommended for simplicity).
- "Featured" toggle promotes an item to homepage/section prominence slots.
- Preview: authenticated "preview draft" link so the journalist can view unpublished content on the live site design before publishing.

## 5. Draft / Published / Scheduled States

| State | Visible on public site? | Editable? |
|---|---|---|
| Draft | No | Yes |
| Scheduled | No (until `published_at`) | Yes |
| Published | Yes | Yes (edits go live immediately) |

## 6. External Article Workflow

1. Journalist selects "External Work" as source type.
2. Selects (or creates inline) a Publication.
3. Enters original URL, published date, short description/summary, content type, optional featured image.
4. Saves as Draft/Scheduled/Published — this only controls the *listing* on his own site; it never affects the original article.
5. Public detail page renders the summary and a clearly labeled outbound "Read Original Article →" link.

## 7. Media Management

- Central library, drag-drop upload, image alt text required before an image can be attached to published content (SEO/accessibility guardrail).
- Documents (PDFs of publications/reports) uploaded here and attached where relevant.
- Images can also be **imported from a URL** on the profile photo field, as an
  alternative to uploading a file. Paste the URL and the image downloads,
  re-encodes and appears in the upload field, ready to save.

  The image is **downloaded once and stored on our own disk** — the public site
  never references the origin URL. That keeps the site working if the other site
  goes away or blocks hotlinking (the outlet's own images do), avoids handing a
  third party a request from every visitor, and means an imported image goes
  through exactly the same validation as an upload: type sniffed from the bytes,
  EXIF stripped, dimensions bounded, converted to WebP when that is smaller.

  Because the **server** does the fetching, the URL is treated as hostile input:
  private/loopback/link-local addresses, non-HTTP schemes and redirects into
  private networks are all refused. See `SECURITY.md §13.1`.

  The URL field is not a database column and is never saved — it hands its result
  to the upload field and clears itself.

## 8. SEO Management

- Per-content-item SEO fields (meta title, meta description, OG image override, canonical URL override) editable inline within the content form (not a separate resource) to reduce admin friction.
- Global SEO defaults (site title template, default OG image, social handles) under Settings.
- Redirects resource for managing slug changes.

## 9. Profile Management

There is exactly one author, so the journalist profile is a **singleton**, not a
collection. Two separate pages, deliberately named apart:

| Page | URL | Holds |
|---|---|---|
| **Author profile** | `/admin/journalist-profile/{id}/edit` | The *public* identity: name, job title, portrait, short/long bio, beats, social links, publications. |
| **My profile** | `/admin/profile` | The *account*: name, email, and the sign-in passcode. |

The Author profile resource is **edit-only** — no index page and no create
route, so there is no URL on which a second profile can be made and no "New"
button anywhere. Its nav entry is registered explicitly on the panel (see
`AdminPanelProvider::navigationItems()`) because Filament v5 deliberately
returns no nav item for a resource that has no index page
(`Resources\Resource\Concerns\HasNavigation::getNavigationItems`).

This is a correctness requirement, not a simplification. The public site
resolves the profile in seven separate places (home, about, contact, feed,
header, footer, 404) through `JournalistProfile::current()`, which orders by
`id`. A bare `first()` — which is what this replaced — has no `ORDER BY`, so a
second row would have had the homepage, the footer and the 404 page each
independently pick a *different* profile, with nothing throwing anywhere.
`CareerHistory`, `Education` and `Award` additionally `belongsTo` a profile
through a select box, so a duplicate would silently reassign the journalist's
own career and awards to the wrong person.

- Nested/repeatable resources for Career History, Education, Awards, each
  sortable via drag-and-drop order. All three attach to the single profile.
- `User::journalistProfile()` is a `HasOne`, matching the singleton rule.

## 10. Independent Publishing

Because `users.role` defaults to `owner` and no policy currently requires a review step, the journalist can create and publish any content type end-to-end without another party's involvement. This is enforced via Filament's authorization policies being permissive for the `owner` role in V1, while the underlying `status` enum and `role` column are already shaped to support adding a `pending_review` state and an `editor` role later (see `DATABASE.md` §4).
