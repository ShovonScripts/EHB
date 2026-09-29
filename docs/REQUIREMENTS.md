# REQUIREMENTS.md

Requirement IDs are grouped by domain: `FR-1xx` Public Website, `FR-2xx` Content, `FR-3xx` Journalist Profile, `FR-4xx` Admin, `NFR-xxx` Non-functional.

## 1. Public Website Requirements

| ID | Requirement |
|---|---|
| FR-101 | The homepage shall present the journalist's identity, current role, featured work, latest reporting, selected external publications, and a contact call-to-action. |
| FR-102 | The About page shall display biography, career history, education, and awards. |
| FR-103 | The site shall provide an Articles section listing internally hosted news/reporting, newest first. |
| FR-104 | The site shall provide a Work section listing externally published work with links to the original source. |
| FR-105 | The site shall provide an Investigations section for investigative journalism pieces. |
| FR-106 | The site shall provide an Interviews section for interviews conducted by the journalist. |
| FR-107 | The site shall provide an Opinions section for opinion/analysis pieces. |
| FR-108 | The site shall provide a Multimedia presentation (video/photo story) either as a dedicated section or as a content-type filter within the archive. |
| FR-109 | The site shall provide a Publications page listing outlets the journalist has written for. |
| FR-110 | The site shall provide topic-based archive pages (`/topics/{slug}`). |
| FR-111 | The site shall provide a unified `/archive` view spanning all content types, filterable by type/category/tag/topic/date. |
| FR-112 | The site shall provide a search page returning results across all content types. |
| FR-113 | The site shall provide a Contact page with a message form. |
| FR-114 | Every content detail page shall visually and structurally distinguish Internal Articles (full text on-site) from External Works (metadata + outbound link). |
| FR-115 | External Work listings shall display: title, short description, published date, publication name/logo, and a clearly labeled "Read Original Article →" outbound link. |
| FR-116 | Awards and achievements shall be listed on the About/Awards section with year, awarding body, and description. |

## 2. Content Requirements

| ID | Requirement |
|---|---|
| FR-201 | The system shall support Internal Articles with full rich-text/body content hosted on-site. |
| FR-202 | The system shall support External Works consisting of metadata and an outbound URL, with no full body required. |
| FR-203 | The system shall support Draft, Scheduled, and Published statuses for all content types. |
| FR-204 | Scheduled content shall automatically become Published at its scheduled datetime. |
| FR-205 | The system shall support Categories as a single-select or limited-select taxonomy per content item. |
| FR-206 | The system shall support Tags as a multi-select, free-form-ish taxonomy per content item. |
| FR-207 | The system shall support Topics as a curated, higher-level taxonomy distinct from tags, used to power `/topics/{slug}`. |
| FR-208 | The system shall support marking content items as "Featured" for homepage/section prominence. |
| FR-209 | The system shall support manually or automatically surfaced "Related Content" on content detail pages. |
| FR-210 | The system shall support all content types (news, investigation, interview, opinion, multimedia, other) as either Internal or External. |

## 3. Journalist Profile Requirements

| ID | Requirement |
|---|---|
| FR-301 | The system shall store a single journalist profile: name, title, photo, short bio, long bio. |
| FR-302 | The system shall store career history entries (role, organization, date range, description). |
| FR-303 | The system shall store education entries (institution, degree/program, date range). |
| FR-304 | The system shall store awards (title, awarding body, year, description, optional link). |
| FR-305 | The system shall store a list of publications the journalist has written for (name, logo, URL). |
| FR-306 | The system shall store skills/areas of reporting (beats) as a simple tag-like list. |
| FR-307 | The system shall store social/professional profile links (e.g., X/Twitter, LinkedIn, email). |

## 4. Admin Requirements

| ID | Requirement |
|---|---|
| FR-401 | The admin panel shall provide CRUD for all content types. |
| FR-402 | The admin panel shall provide a media library for images/documents used across content. |
| FR-403 | The admin panel shall provide profile, career, education, and awards management. |
| FR-404 | The admin panel shall provide per-item SEO fields (meta title, meta description, OG image, canonical override). |
| FR-405 | The admin panel shall provide a contact-message inbox for messages submitted via the public contact form. |
| FR-406 | The admin panel shall provide global settings (site identity, default SEO, social links, analytics IDs). |
| FR-407 | The admin panel shall allow the journalist to publish content independently, with no mandatory approval step in V1. |
| FR-408 | The underlying role/permission architecture shall support adding an Editor/Reviewer role in a future version without schema changes to core content tables. |

## 5. Non-Functional Requirements

| ID | Requirement |
|---|---|
| NFR-001 (SEO) | Pages shall render server-side with complete meta tags, structured data, and crawlable links; no critical content shall be JS-only. |
| NFR-002 (Performance) | Public pages shall target sub-2.5s LCP on a standard broadband connection; images shall be optimized/responsive. |
| NFR-003 (Accessibility) | Public site shall target WCAG 2.1 AA: semantic HTML, sufficient contrast, keyboard navigability, alt text fields for images. |
| NFR-004 (Security) | Admin area shall require authentication; all forms shall be CSRF-protected; inputs sanitized against XSS/SQLi. |
| NFR-005 (Responsive) | The site shall be fully usable on mobile, tablet, and desktop breakpoints. |
| NFR-006 (Maintainability) | Codebase shall follow Laravel conventions and a content architecture that avoids redundant, near-duplicate models. |
| NFR-007 (Scalability) | Schema shall accommodate growth to thousands of content items and, later, multiple contributors without redesign. |
| NFR-008 (Backup) | Database and media shall be backed up on a regular automated schedule. |
| NFR-009 (Error Handling) | User-facing errors (404, 500, form validation) shall be handled gracefully with on-brand error pages. |
| NFR-010 (Bilingual Content) | Content fields shall be able to store Bangla-script text correctly (UTF-8 throughout); full UI localization is out of scope for V1. |
