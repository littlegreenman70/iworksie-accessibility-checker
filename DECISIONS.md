# Decisions

Choices made while building v1.0.0 without asking (Cormac asked for best judgement; review in the morning).

| Topic | Decision | Why |
|---|---|---|
| Slug / prefix | `iworksie-accessibility-checker`, `iwieac_` / `IWIEAC_`, namespace `IWorksIE\AccessibilityChecker` | Build plan conventions (prefix "ac"). |
| Engine | axe-core 4.14.1 bundled in `assets/vendor/axe-core/` | Industry standard, MPL-2.0 (GPL-compatible). wordpress.org forbids loading JS from a CDN. |
| Where the scan runs | In the admin's browser, one same-origin iframe at a time (1280×900 viewport, shown as a small live preview) | Tests the real rendered page incl. JS; no server load, no third party. |
| Injecting axe | The admin page injects `axe.min.js` into the iframe document | Works even when a theme forgets `wp_footer()`. Scan-mode URL (`?iwieac_scan=<nonce>`) only hides the admin bar. |
| Nested iframes | `iframes: false` | YouTube/Maps embeds are third-party and cross-origin; axe would wait up to 60 s for each. |
| URL list | Built from WordPress (home, posts page, template samples, published posts of chosen public post types), not by parsing a sitemap | Works without an SEO plugin, respects post types, newest first, capped (default 500, max 5,000). |
| Template samples | One archive per post type with archives, top term per public taxonomy, one author archive, search results, a 404 URL | Same template = same theme markup; scanning every category page adds time, not findings. |
| Storage | Three custom tables per site, created with dbDelta on activation and lazily on `admin_init` (covers multisite network activation beyond the first 100 sites) | Results for thousands of pages would bloat `wp_options`; SQL aggregation for the Issues tab. |
| Nodes stored | First 30 elements per rule per page (real count kept) | Keeps rows small; enough to fix the pattern. |
| Capability | `manage_options` (filter `iwieac_capability`), also on multisite | Site admins should check their own site. Note: if Health Check is the "winning" core copy on multisite it registers the top menu with `manage_network_options`; that is a core-level edge case to revisit in core. |
| Dismissals | Per element on one page, per element on every page, or the whole rule site-wide; reason required, note optional, user + time recorded; Restore anywhere | Equalize's best UX, without the Pro wall. |
| Score | rules passed / (passed + failed) per page, summed for the site | Simple, explainable; labelled "Automated score". |
| Best-practice rules | On by default, switchable; shown as "Best practice" not a WCAG criterion; never counted as a WCAG failure in the report | Useful advice without overstating legal risk. |
| Report | Markdown via core `Report` (copy + single-use download), every WCAG 2.2 A/AA criterion with EN 301 549 v3.2.1 clause (`9.x.x.x`); 2.2-only criteria marked "WCAG 2.2 only" | EAA angle from the build plan; honest "Manual check needed" for criteria axe cannot test. |
| WCAG 4.1.1 Parsing | Left out of the table | Removed in WCAG 2.2; axe marks it obsolete. |
| Highlighter | Front-end `?iwieac_highlight=<id>&_iwieac=<nonce>` for admins only; outlines elements and a keyboard-usable panel | "View on page" without an editor integration. |
| Row action + admin bar link | "Check accessibility" on published, viewable, non-password posts; admin bar on the front end (filter `iwieac_show_admin_bar_link`) | Fast single-page checks from where people work. |
| Block editor sidebar | Not in v1 | Keeps v1 small; candidate for 1.1. |
| WP-CLI | None | The engine needs a browser. |
| Error codes | Browser sends a code (`timeout`, `frame_blocked`, `other_site`, `script_blocked`, `axe_error`), PHP maps it to translated text | No free text from the browser stored as messages. |
| install-wp-tests tag matching | Resolves X.Y to the latest X.Y.Z and checks out matching `tags/X.Y.Z` tests (not trunk) | WP 6.5 integration (single + multisite) failed because trunk's mock-mailer needs `class-wp-phpmailer.php` absent from 6.5. |
