# DESIGN_SYSTEM.md

## 1. Visual Direction

Editorial, minimal, serious, credible — closer to a well-designed masthead or literary magazine than a "creator" personal site. Typography carries the design; color and decoration stay restrained. No gradients-as-decoration, no card-heavy dashboards-as-homepage, no social-feed aesthetics.

## 2. Typography

**English:**
- Headlines/editorial display: **Playfair Display** or **Libre Baskerville** — serif, credible, editorial.
- Body/UI: **Inter** or **Source Sans 3** — clean, highly legible at small sizes.
- Recommendation: **Libre Baskerville** for headlines (slightly more restrained than Playfair, reads less "wedding invitation," more "newspaper") + **Inter** for body/UI. Playfair is a reasonable alternative if a more dramatic hero treatment is wanted.

**Bangla:**
- Headlines: **Noto Serif Bengali** (pairs with the Latin serif headline in tone).
- Body/UI: **Noto Sans Bengali** (pairs with Inter).
- Both Noto families are recommended as-is — they're the most complete, professionally hinted Bengali web fonts available and pair predictably with the Noto Latin metrics.

**Scale (suggested, rem-based):** display 3–3.5rem / h1 2.25rem / h2 1.75rem / h3 1.375rem / body 1.0625rem / small 0.875rem, with a 1.5–1.6 line-height on body text for long-form readability.

## 3. Color

Restrained, editorial palette rather than a bright brand palette:

- **Ink (primary text):** near-black, e.g. `#1A1A1A`.
- **Paper (background):** warm off-white, e.g. `#FAF9F6` (avoid pure `#FFFFFF` for long-form reading comfort).
- **Accent (single, used sparingly):** a deep, credible accent — e.g. a muted crimson `#8C1D18` or deep navy `#1B2A4A` — used only for links, active states, and small highlights (e.g., "Investigation" badge), never as large color blocks.
- **Muted/secondary text:** mid-gray `#5B5B5B`.
- **Borders/dividers:** hairline `#E4E1D9`.
- Dark mode (optional, phase-2): invert to near-black paper `#121212` / off-white ink `#EDEBE4`, same restrained accent.

## 4. Spacing & Grid

- 8px base spacing unit.
- Content max-width ~720px for article body (optimal reading line length ~65–75 characters).
- Wider container (~1200px) for archive grids/homepage.
- 12-column responsive grid for listing pages, collapsing to 1–2 columns on mobile.

## 5. Layout Patterns

- Homepage: hero → featured grid → latest feed → publication strip → investigations spotlight → about teaser → contact CTA (single-column narrative flow, not a dense dashboard).
- Archive/listing pages: filter bar (sticky on desktop, collapsible on mobile) + list/grid of ContentCards.
- Detail pages: centered single-column reading column, generous margins, pull-quote style for dek/summary.

## 6. Buttons & Links

- Primary action buttons: minimal — text + accent-colored underline or thin border, not filled rounded pills (avoids "startup SaaS" look). One filled-button style reserved for the single most important action per page (e.g., "Send Message" on Contact).
- In-body links: accent color, underline on hover (or always-on subtle underline for accessibility).
- "Read Original Article →" external CTA: distinct treatment (icon + accent text) so external vs. internal is unmistakable at a glance.

## 7. Cards

Used sparingly and consistently: ContentCard (thumbnail, type badge, title, dek, date, source badge). Avoid stacking multiple competing card styles on one page — one card component, reused everywhere, with type/source badges as the only variation.

## 8. Article Typography

- Body serif or humanist sans at 17–18px equivalent, 1.6 line-height.
- Drop-cap or styled first paragraph optional for internal long-form pieces (investigations) only — not applied uniformly.
- Pull-quotes: larger serif italic, accent-colored left border.
- Footnote/citation style for source attribution where relevant.

## 9. Image Ratios

- Featured/hero images: 16:9.
- ContentCard thumbnails: 4:3 (consistent grid rhythm).
- Portrait/profile photo: 1:1 or 4:5.
- All images require alt text (enforced at CMS level per `ADMIN_PANEL.md`).

## 10. Navigation

- Simple horizontal nav on desktop, hamburger on mobile.
- No more than 7–8 top-level nav items (collapse Multimedia/Opinions under an "More" menu if needed to stay uncluttered) — recommend keeping Articles, Investigations, Interviews, Opinions, Publications, About, Contact, and a search icon, folding Multimedia into the Archive filters if nav feels crowded.

## 11. Footer

- Short bio line, social/professional links, publication logos (small, grayscale), copyright, and a one-line disclaimer: "Personal website of Emrul Hasan Bappi. Not an official publication of The Daily Star or any other outlet." This reinforces `PROJECT_PLAN.md` positioning directly in the UI.

## 12. Responsive Behavior

- Mobile-first breakpoints (e.g., 375 / 768 / 1024 / 1280).
- Filter bars collapse into a bottom-sheet or dropdown on mobile.
- Article body retains generous margins even on mobile — never edge-to-edge text.

## 13. Accessibility Considerations

- Minimum 4.5:1 contrast for body text against paper background.
- Focus states visible on all interactive elements (accent-colored outline).
- Alt text required for all published images.
- Semantic heading hierarchy (single H1 per page).
- Bangla text: ensure line-height is increased slightly (Bengali script needs more vertical room) when Bangla content is present in a block.
