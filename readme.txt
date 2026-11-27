=== iWorks.ie Accessibility Checker ===
Contributors: littlegreenman
Tags: accessibility, wcag, a11y, ada, european accessibility act
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free full-site accessibility checker built on axe-core. Plain-English fixes and a WCAG 2.2 AA / EN 301 549 report. No overlay, no upsells.

== Description ==

iWorks.ie Accessibility Checker scans your whole site for accessibility problems, explains each one in plain English, and gives you a report mapped to WCAG 2.2 Level AA and EN 301 549, the European standard behind the European Accessibility Act (EAA) and the Web Accessibility Directive.

**Everything is free.** Full-site scans, every post type (pages, posts, WooCommerce products, custom post types), archives and templates. Nothing is locked behind a Pro version, because there is no Pro version.

= How it works =

The scan runs in your own browser. Each page is opened out of sight in the admin and tested with [axe-core](https://github.com/dequelabs/axe-core), the open-source engine from Deque that accessibility professionals use. Because it tests the real rendered page, it sees your theme, page builder, plugins and JavaScript exactly as visitors do, which a server-side scan can miss.

= What you get =

* **Full-site scan**: home page, one of each template (archives, categories, author, search, 404) and all published content of the post types you choose. Resumable if you close the tab.
* **Check one page** from the plugin screen, the "Check accessibility" row action on any post or page, or the admin bar while viewing the site.
* **Problems vs Needs review**: definite failures are kept apart from items a person needs to look at (for example text over a background image).
* **Plain-English explanations**: what is wrong, why it matters, and how to fix it in WordPress (block editor settings, Media Library, theme options), with a link to the technical guidance.
* **Show on page**: opens the page with the affected elements outlined and a small panel to step through them.
* **Issues by type**: each issue is listed once with every page it affects, so you can fix a template once instead of page by page.
* **Dismiss with a reason** (false positive, checked by a person, third-party content, not relevant), on one page or on every page, with who and when recorded. Restore any time.
* **WCAG 2.2 AA / EN 301 549 report**: every success criterion with Fails / Needs review / No automated failures / Manual check needed, the EN 301 549 clause number, affected pages, and a manual-testing checklist. Copy or download as Markdown.

= What it does not do =

* **No overlay widget, ever.** Accessibility overlays do not make a site compliant and are widely criticised by disabled users and the accessibility community. This plugin helps you find and fix the real problems instead.
* **It does not change your site.** It only reads pages and stores its own results.
* **It is not a compliance certificate.** Automated testing finds roughly a third of accessibility problems. The report says so clearly and lists the manual checks to do next.

= Privacy =

No data leaves your site. The scan runs in your browser against your own pages, axe-core is bundled with the plugin (no CDN), there is no tracking, no account and no external API.

Need a full manual audit, fixes or an accessibility statement? iWorks can help: https://www.iworks.ie

== Installation ==

1. Upload the `iworksie-accessibility-checker` folder to `/wp-content/plugins/`, or install it from Plugins → Add New.
2. Activate the plugin.
3. Open **iWorks Tools → Accessibility** and start a scan.

Keep the tab open while a full scan runs. Each page takes a few seconds. If you close it, use Resume to carry on where it stopped.

== Frequently Asked Questions ==

= Is it really free for the whole site? =

Yes. Every post type, every page, archives and templates. There is no paid version.

= Will it slow my site down? =

No. Nothing runs for visitors. The scan only happens while an administrator has the Scan screen open, one page at a time.

= Why does a page say it "could not be checked"? =

Usually a security plugin or server header stops the page from loading inside the admin (X-Frame-Options: DENY or a Content-Security-Policy frame-ancestors rule). Allowing same-origin framing (SAMEORIGIN) fixes it. Pages that redirect to another domain, or take more than 45 seconds to load, are also skipped with an explanation.

= Does a clean scan mean my site is compliant with the EAA or WCAG? =

No. It means no automated failures were found on the pages checked. Things like keyboard use, meaningful alt text and caption quality need a person to test. The report includes a checklist for that.

= What is "Needs review"? =

Items the engine could not decide automatically, for example text over an image where the contrast depends on the picture. Look at each one and dismiss it if it is fine.

= Does it work with page builders and WooCommerce? =

Yes. It tests the final page in the browser, so it works with any theme or builder. WooCommerce products and any other public post type can be included in the scan.

= Is it multisite compatible? =

Yes. Each site administrator scans their own site and results are stored per site.

= Which WCAG version does it test? =

WCAG 2.0, 2.1 and 2.2 at Levels A and AA (the level required by EN 301 549 and the EAA), plus optional best-practice checks that you can switch off.

== Screenshots ==

1. Overview with automated score, totals and the top problems to fix.
2. Full-site scan running, with a live preview of the page being checked.
3. Page details: plain-English explanation, elements, Show on page and Dismiss.
4. Issues grouped by type with the pages each one affects.
5. WCAG 2.2 AA / EN 301 549 report.

== Third-party libraries ==

* axe-core 4.14.1 by Deque Systems, Mozilla Public License 2.0 (compatible with the GPL). Source: https://github.com/dequelabs/axe-core. The minified file is the unmodified build from the npm package `axe-core`.

== Changelog ==

= 1.0.0 =
* First release.
