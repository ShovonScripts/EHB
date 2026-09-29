# DATABASE.md

## 1. Key Architectural Decision: Unified `content_items` Table

**Option A — Separate tables per type** (`articles`, `investigations`, `interviews`, `opinions`, ...)
- Pros: type-specific schemas are very explicit; simple per-type queries.
- Cons: duplicates shared fields (title, slug, SEO, dates, status, media, tags) four-plus times; tags/categories/search must join across N tables; cross-type "archive" and "search" pages require UNION queries across every table; adding a new type means a new table + new admin resource + new everything.

**Option B — Single `content_items` table with a `content_type` discriminator column, plus `source_type` (internal/external)**
- Pros: one schema, one set of taxonomy joins, one search index, one admin resource with type-aware forms; archive/search pages are trivial (`WHERE status = published ORDER BY published_date`); adding a new content_type is a one-line enum addition.
- Cons: some nullable columns only relevant to certain types (e.g., `interviewee_name` only for interviews) or type-specific data goes in a JSON `meta` column.

**Recommended approach: Option B.**

**Reason:** The content types share the overwhelming majority of their fields and all cross-cutting features (tags, topics, categories, SEO, search, featured/related). A unified table keeps the admin, search, and archive simple, and the small amount of type-specific data is cleanly handled with a few nullable columns plus an optional `meta` JSON column, rather than paying the structural cost of four to seven parallel tables.

## 2. Proposed Tables

### `users`
**Purpose:** Authentication for the journalist (and, later, additional contributors/editors).
Columns: id, name, email, password, role (enum: owner, editor — editor unused in V1 but reserved), timestamps.
Relationships: has-one `journalist_profiles` (for the owner); has-many `content_items` (author).

### `journalist_profiles`
**Purpose:** The public-facing profile of the journalist (single row in V1, FK-able to `users` for future multi-journalist support).
Columns: id, user_id (FK), name, title, photo_media_id, short_bio, long_bio, timestamps.
Relationships: belongs to `users`; has-many `career_history`, `education`, `awards`; many-to-many `publications`.

### `career_history`
Columns: id, journalist_profile_id (FK), role, organization, start_date, end_date (nullable), description, sort_order.
Relationships: belongs to `journalist_profiles`.

### `education`
Columns: id, journalist_profile_id (FK), institution, program, start_date, end_date (nullable), sort_order.
Relationships: belongs to `journalist_profiles`.

### `awards`
Columns: id, journalist_profile_id (FK), title, awarding_body, year, description, url (nullable), media_id (nullable, for a certificate/photo), sort_order.
Relationships: belongs to `journalist_profiles`.

### `publications`
**Purpose:** Generic registry of outlets (The Daily Star and any other).
Columns: id, name, slug, logo_media_id (nullable), website_url (nullable), description (nullable), timestamps.
Relationships: has-many `content_items` (where source_type = external); many-to-many `journalist_profiles`.

### `content_items`
**Purpose:** The unified model for all journalistic work (news, investigation, interview, opinion, video, photo story, other), internal or external.
Columns:
- id
- author_id (FK → users)
- content_type (enum: news, investigation, interview, opinion, video, photo_story, other)
- source_type (enum: internal, external)
- title
- slug
- summary
- body (nullable — required for internal, null for external)
- featured_image_media_id (nullable)
- category_id (FK, nullable)
- publication_id (FK, nullable — required for external)
- external_url (nullable — required for external)
- interviewee_name (nullable — interview-specific)
- interviewee_title (nullable — interview-specific)
- video_url (nullable — video-specific)
- meta (JSON, nullable — catch-all for rare type-specific fields, avoids constant migrations)
- is_featured (boolean)
- status (enum: draft, scheduled, published)
- published_at (datetime, nullable)
- reading_time_minutes (nullable)
- seo_title, seo_description, seo_og_image_media_id, canonical_url_override (nullable — could also live in `seo_metadata`, see below)
- timestamps

Relationships: belongs to `users` (author), belongs to `categories` (nullable), belongs to `publications` (nullable), many-to-many `tags`, many-to-many `topics`, many-to-many `content_items` (self, via `related_content` pivot), has-many `media` (gallery/attachments).

### `categories`
Columns: id, name, slug, description (nullable), sort_order.

### `tags`
Columns: id, name, slug.

### `content_item_tag` (pivot)
Columns: content_item_id, tag_id.

### `topics`
**Purpose:** Curated, editorial groupings powering `/topics/{slug}` dossier pages.
Columns: id, name, slug, description (nullable), featured_image_media_id (nullable).

### `content_item_topic` (pivot)
Columns: content_item_id, topic_id.

### `related_content` (pivot, self-referencing)
Columns: content_item_id, related_content_item_id.

### `media`
**Purpose:** Central media library for images, video/audio references, and documents.
Columns: id, type (enum: image, video, audio, document), file_path, disk, original_filename, alt_text (nullable), caption (nullable), uploaded_by (FK → users), timestamps.

### `pages`
**Purpose:** Small number of static/semi-static pages (About long-form intro block, Contact intro text) that aren't part of the content archive, if the journalist wants CMS-editable copy outside of dedicated fields.
Columns: id, slug, title, body, seo fields, timestamps. *(Optional — only needed if hard-coding About/Contact copy in Blade isn't flexible enough; evaluate during implementation.)*

### `contacts`
**Purpose:** Messages submitted via the public contact form.
Columns: id, name, email, subject (nullable), message, is_read (boolean), created_at.

### `settings`
**Purpose:** Global site settings (key-value or single-row config table).
Columns: id, key, value (text/JSON), timestamps. *(Key-value recommended for flexibility.)*

### `seo_metadata`
**Not created as a separate table.** SEO fields are kept directly on `content_items` (and `pages`) as nullable columns, since every entity needing SEO already has a natural home for those columns and a 1:1 side table would only add joins without benefit at this scale.

### `redirects`
**Purpose:** Manage slug changes / legacy URLs without breaking SEO or inbound links.
Columns: id, from_path, to_path, status_code (301/302), timestamps.

### `activity_logs`
**Purpose:** Lightweight audit trail (who changed/published what, when) — useful even pre-editor, and essential once an editor role exists.
Columns: id, user_id, action, subject_type, subject_id, changes (JSON, nullable), created_at.

## 3. Tables Considered and Deliberately Excluded (for V1)

- **Separate `investigations` / `interviews` / `opinions` tables:** merged into `content_items` (see §1).
- **`article_tag` as separate from a generic pivot:** renamed/generalized to `content_item_tag` since "article" isn't a standalone table.
- **Whistleblower/tip submission tables:** explicitly deferred — see `SECURITY.md`.

## 4. Status & Role Extensibility Note

`content_items.status` (draft/scheduled/published) and `users.role` (owner/editor) are intentionally modeled now, even though only `owner` is used in V1, so that a future editorial workflow (e.g., adding `status: pending_review` and enforcing that only `editor`/`owner` roles can transition to `published`) requires an enum addition and a permission-policy change — not a schema rebuild.
