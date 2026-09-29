# ROADMAP.md

## Phase 0 — Planning
**Objectives:** Finalize scope and architecture before writing code.
**Tasks:** Review/approve this docs/ set with the journalist; resolve open questions (see end of summary); confirm technology assumptions (DB engine, hosting target).
**Dependencies:** None.
**Completion criteria:** All 10 planning documents reviewed and signed off; open questions list resolved or explicitly deferred.

## Phase 1 — Laravel Foundation
**Objectives:** Stand up the base application.
**Tasks:** Install Laravel; configure environment (.env, DB connection); install Filament; set up base auth (owner account); configure storage disks for media.
**Dependencies:** Phase 0 sign-off.
**Completion criteria:** Fresh Laravel app boots locally, admin login works, empty Filament panel accessible.

## Phase 2 — Database & CMS Core
**Objectives:** Implement the data layer per `DATABASE.md`.
**Tasks:** Migrations for all approved tables; Eloquent models + relationships; base Filament resources for `content_items`, `categories`, `tags`, `topics`, `publications`, `media`.
**Dependencies:** Phase 1.
**Completion criteria:** All tables migrated; admin CRUD works for content items (internal + external) and taxonomies.

## Phase 3 — Journalist Profile
**Objectives:** Implement profile-related data and admin screens.
**Tasks:** `journalist_profiles`, `career_history`, `education`, `awards` migrations/models; Filament profile edit screen + repeatable sub-resources.
**Dependencies:** Phase 2.
**Completion criteria:** Journalist can fully populate bio, career, education, and awards from the admin.

## Phase 4 — Content Publishing
**Objectives:** Complete the type-aware content authoring experience.
**Tasks:** Conditional form fields by `content_type`/`source_type`; draft/scheduled/published lifecycle; featured toggle; related-content picker; media attachment; SEO fields on the content form.
**Dependencies:** Phase 2.
**Completion criteria:** Journalist can create, schedule, and publish every content type (internal and external) end-to-end from the admin.

## Phase 5 — Public Website
**Objectives:** Build the public-facing routes/pages per `FRONTEND.md`.
**Tasks:** Homepage, About, per-type listing + detail pages, Publications pages, Topic pages, Archive, Contact; shared Header/Footer/ContentCard components; apply `DESIGN_SYSTEM.md`.
**Dependencies:** Phases 2–4 (needs real content-authoring to test against).
**Completion criteria:** All routes in `FRONTEND.md` render correctly for both internal and external content, responsive across breakpoints.

## Phase 6 — Search & Archive
**Objectives:** Implement `/search` and the advanced `/archive` filters.
**Tasks:** DB-driven full-text/LIKE search across title/summary/body; archive filter bar (type/category/tag/topic/publication/date); pagination.
**Dependencies:** Phase 5.
**Completion criteria:** Search returns relevant results across all content types; archive filters combine correctly without errors.

## Phase 7 — SEO
**Objectives:** Implement the full SEO layer per `SEO.md`.
**Tasks:** Meta tags, canonical logic (internal vs. external self-canonicalization), OG/Twitter cards, structured data (Person, Article, CreativeWork citation pattern), sitemap.xml, robots.txt, RSS feed(s), breadcrumbs.
**Dependencies:** Phase 5.
**Completion criteria:** All page types validate against structured-data testing tools; sitemap includes all published content; robots.txt correctly excludes admin/search/drafts.

## Phase 8 — Security & Performance
**Objectives:** Harden the application per `SECURITY.md`.
**Tasks:** Security headers, CSP, rate limiting (login/contact/search), file upload validation/sanitization, activity logging, backup automation, image optimization/responsive images, caching strategy for listing pages.
**Dependencies:** Phases 4–7.
**Completion criteria:** Security checklist in `SECURITY.md` fully implemented; Lighthouse/perf targets from `REQUIREMENTS.md` NFR-002 met.

## Phase 9 — Testing
**Objectives:** Verify correctness and resilience before launch.
**Tasks:** Feature tests for content publishing lifecycle, internal/external rendering branch, contact form, search; accessibility audit against WCAG 2.1 AA; backup restore-test; cross-browser/device QA.
**Dependencies:** Phases 1–8.
**Completion criteria:** Test suite passing; accessibility audit issues resolved or triaged; successful backup restore verified.

## Phase 10 — Deployment
**Objectives:** Launch to production.
**Tasks:** Provision hosting/DB, configure environment secrets, HTTPS/HSTS, deploy pipeline, DNS cutover, post-launch smoke test (sitemap, forms, admin login, key pages).
**Dependencies:** Phase 9.
**Completion criteria:** Site live on production domain, all critical paths verified working, monitoring/backups active.

## Phase 11 — Future Enhancements (Post-V1, not scheduled)
**Objectives:** Track deferred ideas without scope-creeping V1.
**Candidates:** Editor/reviewer approval workflow (role + status extension already supported architecturally); multi-contributor support; newsletter distribution; analytics dashboard; dark mode; full bilingual UI; richer multimedia (podcast/audio) type; source-submission/whistleblower channel (see `SECURITY.md` §20 — requires dedicated security work, not an incremental add-on).
**Dependencies:** None currently — revisit after V1 launch and usage data.
**Completion criteria:** N/A — this phase is a backlog, not a committed scope.
