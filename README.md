# iWorks.ie Accessibility Checker

Free full-site WordPress accessibility checker for [wordpress.org](https://wordpress.org/plugins/iworksie-accessibility-checker/), built on [axe-core](https://github.com/dequelabs/axe-core) and [`iworks-tools-core`](https://github.com/littlegreenman70/iworks-tools-core).

## Requirements

- WordPress 6.5+
- PHP 7.4+ (code is written for 7.4; checked with PHPCompatibilityWP for 7.4 and up)
- Composer (development)

## How the scan works

1. **Scan tab** asks WordPress for the list of URLs (`iwieac_build_queue`): home, one sample of each template, and published content of the chosen post types. The list is stored in the `iwieac_queue` option so a scan can be resumed.
2. The browser loads each URL in a same-origin iframe with `?iwieac_scan=<nonce>` (which hides the admin bar for that request), injects the bundled `assets/vendor/axe-core/axe.min.js`, runs it with the WCAG 2.x A/AA tags (+ `best-practice` if enabled), trims the result and posts it to `iwieac_save_result`.
3. PHP validates the payload strictly (`Scan\PayloadSanitizer`) and stores it in three per-site tables: `{prefix}iwieac_pages`, `{prefix}iwieac_issues`, `{prefix}iwieac_dismissals`.

Nothing is sent outside the site.

## Local development

```bash
composer install
ln -sfn "$(pwd)" /opt/homebrew/var/www/www.iworks.new/wp-content/plugins/iworksie-accessibility-checker
wp plugin activate iworksie-accessibility-checker --path=/opt/homebrew/var/www/www.iworks.new
```

Core is resolved from the local path `../iworks-tools-core` in development and from the public GitHub VCS repo in CI.

```bash
composer lint
composer analyse
composer test:unit
bin/install-wp-tests.sh iworks_tests_ac root '' 127.0.0.1 latest
composer test:integration
composer build
```

Real-browser end-to-end checks (Playwright + Chromium) scan a throwaway site with known-broken pages, exercise dismiss/restore, the report and the highlighter, and run axe against the plugin's own admin screens:

```bash
WP=wp WP_CORE_DIR=/path/to/clean-wp-copies tests/e2e/setup.sh wp71 8071 iwieac_e2e
php -S 127.0.0.1:8071 -t /tmp/sites/wp71 &
cd tests/e2e && npm install && node run.js http://localhost:8071 local
```

## Updating axe-core

```bash
npm pack axe-core@<version> && tar xzf axe-core-<version>.tgz
cp package/axe.min.js package/LICENSE assets/vendor/axe-core/
```

Then bump `Plugin::AXE_VERSION`, the "Third-party libraries" section of `readme.txt`, and `Wcag::AUTOMATED` if the rule set changed.

## License

GPL-2.0-or-later. axe-core is MPL-2.0 (see `assets/vendor/axe-core/LICENSE`).
