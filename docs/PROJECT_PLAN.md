# PROJECT_PLAN.md

## 1. Project Overview

A custom, professionally designed personal website for a journalist ("Emrul Hasan Bappi") who reports for **The Daily Star**. The site is a **personal/professional portfolio, work archive, and independent publishing platform** — not a Daily Star property, not a generic blog, and not a template-driven personal site.

It exists to consolidate the journalist's professional identity, career history, and body of work — regardless of where each piece was originally published — into one authoritative, searchable destination that he owns and controls.

## 2. Project Objectives

- Establish a single, credible online presence for the journalist's professional identity and career.
- Archive and organize his journalistic output: reporting, investigations, interviews, opinion/analysis, and multimedia.
- Support both content hosted directly on the site and content published elsewhere (The Daily Star or other outlets), without conflating the two.
- Give the journalist full editorial and publishing independence, with no mandatory approval workflow in V1.
- Build on an architecture that can later support an editorial workflow and/or additional contributors without a rebuild.
- Position the journalist favorably for peers, sources, employers, award committees, and readers.

## 3. Target Audience

- Readers following his reporting and investigations.
- Editors, media organizations, and potential employers/collaborators reviewing his portfolio.
- Award committees, fellowship panels, and academic/media researchers.
- Sources seeking to understand his beat and credibility before contacting him.
- Fellow journalists and researchers looking to cite or reference his work.

## 4. Website Positioning

- **Is:** A personal professional portfolio and independent archive of journalistic work, clearly authored and owned by the journalist.
- **Is not:** An official Daily Star publication, a syndication mirror, a generic blog, or a promotional brand site.
- Every external work is clearly attributed to its original publication with an outbound link — never reproduced as if it were the journalist's own outlet's exclusive content.
- Visual tone: editorial, minimal, serious, credible — closer to a masthead-quality personal archive than a "content creator" site.

## 5. Core Features

- Journalist profile: biography, career history, education, skills/beats, social/professional links.
- Unified content archive covering news, investigations, interviews, opinion/analysis, and multimedia.
- Dual content model: **Internal Articles** (hosted in full) and **External Works** (metadata + link-out).
- Publication registry (The Daily Star, and any other outlet), not hard-coded to one brand.
- Categorization via categories, tags, and topics.
- Full-text/metadata search across the whole archive.
- Awards & recognitions, professional publications (books/reports/papers if any).
- Contact page with a message form.
- Admin CMS for independent content management (Laravel + Filament, planned).
- SEO architecture designed to avoid duplicate-content issues with syndicated/external work.

## 6. Content Types

News Article, External Published Work, Investigation, Interview, Opinion/Analysis, Video, Photo Story, Other Work — all detailed in `CONTENT_MODEL.md`. Investigations, interviews, and opinions are treated as **classifications of a single underlying content architecture**, not as isolated, duplicated systems (see `DATABASE.md` for reasoning).

## 7. Public Website Structure

See `FRONTEND.md` for the full route map and page-level detail. High level:

```
/                Homepage
/about           Biography, career, education, awards
/articles        Reporting/news archive
/work            External published work archive
/investigations  Investigative journalism
/interviews      Interviews conducted
/opinions        Opinion/analysis
/publications    List of publications he's written for
/topics/{slug}   Topic-filtered archive
/archive         Full chronological/searchable archive
/search          Site-wide search
/contact         Contact form
```

## 8. Admin Functionality

A Filament-based admin panel (see `ADMIN_PANEL.md`) covering content CRUD across all content types, profile/career/education/award management, media library, publication registry, SEO fields per entry, contact message inbox, and site settings. No mandatory review/approval step in V1; status field (draft/scheduled/published) is journalist-controlled.

## 9. Publishing Approach

- Single-author (the journalist) publishing model for V1.
- Draft → Scheduled → Published lifecycle, journalist-controlled, no gatekeeping.
- Architecture reserves a `role` and `status` design (see `DATABASE.md`, `ADMIN_PANEL.md`) that can later support an editor/reviewer step without schema rebuilds.

## 10. External Publication Support

- A `publications` table represents any outlet (The Daily Star included) generically — never hard-coded.
- External works store: publication, original URL, published date, optional logo, article type, short description.
- Clicking through always sends the reader to the original source; the site never reproduces the full external text.

## 11. Future Extensibility

- Multi-contributor / multi-journalist support.
- Editor/reviewer approval workflow.
- Newsletter/RSS distribution.
- Source submission / tip-line (explicitly deferred — see `SECURITY.md`).
- Multi-language (English/Bangla) content parity.
- Analytics dashboard, richer media (audio/podcast) types.

## 12. Technology Assumptions

- Backend: Laravel (PHP).
- Admin panel: Filament.
- Database: MySQL/PostgreSQL (relational; exact choice deferred to implementation phase).
- Frontend: Blade + Tailwind (+ Alpine.js for light interactivity), server-rendered for SEO strength.
- Search: DB-driven full-text search for V1; upgrade path to Meilisearch/Algolia noted as future option.
- Hosting/infra decisions deferred to `ROADMAP.md` / implementation phase.

## 13. Project Scope (V1)

In scope: journalist profile, unified content archive (internal + external), publications registry, categories/tags/topics, search, admin CMS, contact form, SEO foundation, security foundation.

## 14. Out-of-Scope for V1

- Multi-author / editorial approval workflow.
- Anonymous source submission or whistleblower tooling (see `SECURITY.md` for why, and what would be required later).
- Membership/subscription or paywall features.
- Comments system.
- Native mobile app.
- Multi-language UI switching (content may be bilingual, but a full i18n UI is deferred).
- Advertising/monetization tooling.
