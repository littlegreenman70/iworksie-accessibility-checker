// End-to-end check of the Accessibility Checker in a real WordPress + Chromium.
// usage: node tests/e2e/run.js <baseUrl> <label>   (site prepared by tests/e2e/setup.sh, login admin/admin)
const { chromium } = require('playwright');
const base = process.argv[2];
const label = process.argv[3];
const path = require('path');
const out = process.env.E2E_OUT || path.join(require('os').tmpdir(), 'iwieac-e2e-' + label);
require('fs').mkdirSync(out, { recursive: true });
const results = [];
function check(name, ok, extra) {
  results.push((ok ? 'PASS ' : 'FAIL ') + name + (extra ? ' :: ' + extra : ''));
}
(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 1000 }, deviceScaleFactor: 1 });
  const page = await ctx.newPage();
  const consoleErrors = [];
  page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) consoleErrors.push(m.text()); });
  page.on('pageerror', (e) => consoleErrors.push('pageerror: ' + e.message));
  page.on('dialog', (d) => d.accept());

  await page.goto(base + '/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'admin');
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/);

  const adminPage = base + '/wp-admin/admin.php?page=iworksie-accessibility-checker';
  await page.goto(adminPage);
  check('overview empty state', await page.locator('text=Check your whole site for accessibility problems').isVisible());
  check('menu under iWorks Tools', (await page.locator('#adminmenu a[href*="iworksie-accessibility-checker"]').count()) > 0);
  await page.screenshot({ path: out + '/1-overview-empty.png', fullPage: true });

  // Full scan.
  await page.goto(adminPage + '&tab=scan');
  check('resume hidden before any scan', !(await page.locator('[data-iwieac-resume]').isVisible()));
  await page.click('[data-iwieac-start]');
  await page.waitForSelector('[data-iwieac-message]:has-text("Finished")', { timeout: 240000 });
  const msg = await page.textContent('[data-iwieac-message]');
  check('full scan finished', /Finished\. \d+ pages checked/.test(msg), msg);
  const logCount = await page.locator('[data-iwieac-log] li').count();
  check('log has entries', logCount >= 8, 'entries=' + logCount);
  const errorsInLog = await page.locator('[data-iwieac-log] li.has-error').allTextContents();
  check('no scan errors', errorsInLog.length === 0, errorsInLog.join(' | '));
  check('stop + resume hidden after finish', !(await page.locator('[data-iwieac-stop]').isVisible()) && !(await page.locator('[data-iwieac-resume]').isVisible()));
  await page.screenshot({ path: out + '/2-scan-finished.png', fullPage: true });

  // Overview.
  await page.goto(adminPage);
  const tiles = await page.locator('.iwt-stat-tile__value').allTextContents();
  check('overview tiles', tiles.length === 3, tiles.join(','));
  check('top problems table', (await page.locator('.iwieac-table tbody tr').count()) >= 3);
  await page.screenshot({ path: out + '/3-overview.png', fullPage: true });

  // Pages list → Broken Page detail.
  await page.goto(adminPage + '&tab=pages');
  const topTwo = (await page.locator('.iwieac-pages tbody tr').nth(0).textContent()) + (await page.locator('.iwieac-pages tbody tr').nth(1).textContent());
  check('broken page sorted to the top (search results may tie)', topTwo.includes('Broken Page'));
  await page.click('a.row-title:has-text("Broken Page")');
  await page.waitForSelector('.iwieac-page-head');
  const titles = await page.locator('.iwt-result__title').allTextContents();
  const expect = ['Images are missing alternative text', 'Links have no text', 'ARIA attributes have invalid values', 'Buttons have no accessible name'];
  expect.forEach((t) => check('detail shows: ' + t, titles.includes(t), titles.join(' / ')));
  const contrast = titles.includes('Text does not have enough colour contrast');
  check('contrast issue found (problem or review)', contrast, titles.join(' / '));
  // Expand first card and dismiss the first element on every page.
  const first = page.locator('.iwt-result').first();
  await first.locator('.iwt-result__toggle').click();
  check('accordion opens', await first.locator('.iwt-result__body').isVisible());
  await page.screenshot({ path: out + '/4-page-detail.png', fullPage: true });
  const problemsBefore = await page.locator('.iwt-stat-tile__value').first().textContent();
  await first.locator('summary:has-text("Dismiss")').first().click();
  await first.locator('select[name="reason"]').first().selectOption('confirmed');
  await first.locator('input[name="note"]').first().fill('Checked by hand');
  await Promise.all([page.waitForNavigation(), first.locator('form[data-iwieac-dismiss] button[type="submit"]').first().click()]);
  const problemsAfter = await page.locator('.iwt-stat-tile__value').first().textContent();
  check('dismiss reduces problems', parseInt(problemsAfter, 10) === parseInt(problemsBefore, 10) - 1, problemsBefore + ' -> ' + problemsAfter);

  // Highlighter.
  const hlHref = await page.locator('a:has-text("Show issues on the page")').getAttribute('href');
  const hl = await ctx.newPage();
  hl.on('pageerror', (e) => consoleErrors.push('hl pageerror: ' + e.message));
  await hl.goto(hlHref);
  await hl.waitForSelector('#iwieac-highlight-panel');
  const outlined = await hl.locator('.iwieac-hl').count();
  check('highlighter outlines elements', outlined >= 3, 'outlined=' + outlined);
  await hl.click('#iwieac-highlight-panel button:has-text("Next")');
  check('highlighter position text', /Element 2 of/.test(await hl.textContent('.iwieac-hl-panel__position')));
  await hl.screenshot({ path: out + '/5-highlighter.png' });
  await hl.close();

  // Issues tab.
  await page.goto(adminPage + '&tab=issues');
  check('issues tab groups', (await page.locator('.iwt-group__heading').first().textContent()).startsWith('Problems'));
  await page.screenshot({ path: out + '/6-issues.png', fullPage: true });

  // Report.
  await page.goto(adminPage + '&tab=report');
  const report = await page.textContent('#iwieac-report-source');
  check('report has 1.1.1 failing with EN clause', report.includes('| 1.1.1 Non-text Content | A | 9.1.1.1 | Fails |'));
  check('report marks 2.2-only criteria', report.includes('WCAG 2.2 only'));
  check('report has manual checklist + CTA', report.includes('Manual checks to do next') && report.includes('https://www.iworks.ie'));
  const dl = await page.locator('[data-iwt-download-report]').getAttribute('href');
  const resp = await page.request.get(dl);
  check('report download works', resp.ok() && (await resp.text()).startsWith('# Accessibility report'), String(resp.status()));
  await page.screenshot({ path: out + '/7-report.png', fullPage: true });

  // Dismissed + restore.
  await page.goto(adminPage + '&tab=dismissed');
  check('dismissed lists item', (await page.locator('table tbody tr').count()) === 1);
  await Promise.all([page.waitForNavigation(), page.click('[data-iwieac-restore]')]);
  check('restore empties list', await page.locator('text=Nothing dismissed').isVisible());

  // Row action → single check.
  await page.goto(base + '/wp-admin/edit.php?post_type=page');
  const rowLink = page.locator('.row-actions .iwieac_check a').first();
  check('row action present', (await rowLink.count()) > 0);
  await page.goto(await rowLink.getAttribute('href'));
  await page.waitForSelector('[data-iwieac-message]:has-text("Finished.")', { timeout: 60000 });
  check('single check from row action', (await page.locator('[data-iwieac-log] li').count()) === 1);

  // Admin bar link on the front end, and scan mode hides admin bar.
  await page.goto(base + '/good-page/');
  check('admin bar link on front end', (await page.locator('#wp-admin-bar-iwieac-check a').count()) === 1);
  await page.goto(base + '/good-page/?iwieac_scan=forged');
  check('forged scan nonce keeps admin bar', (await page.locator('#wpadminbar').count()) === 1);

  // Resume: start a scan, stop it, resume it.
  await page.goto(adminPage + '&tab=scan');
  await page.click('[data-iwieac-start]');
  await page.waitForSelector('[data-iwieac-log] li', { timeout: 60000 });
  await page.click('[data-iwieac-stop]');
  await page.waitForSelector('[data-iwieac-message]:has-text("Stopped")', { timeout: 60000 });
  await page.reload();
  const resumeVisible = await page.locator('[data-iwieac-resume]').isVisible();
  check('resume offered after stop + reload', resumeVisible, await page.locator('[data-iwieac-resume]').textContent());
  if (resumeVisible) {
    await page.click('[data-iwieac-resume]');
    await page.waitForSelector('[data-iwieac-message]:has-text("Finished")', { timeout: 240000 });
    check('resume completes', true);
  }

  // Phone width layout sanity.
  await page.setViewportSize({ width: 390, height: 900 });
  await page.goto(adminPage);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  check('no horizontal scroll at 390px', overflow <= 1, 'overflow=' + overflow);
  await page.screenshot({ path: out + '/8-mobile.png', fullPage: true });

  // Self-audit: our own admin screens must pass axe (WCAG 2.2 AA) inside the plugin container.
  await page.setViewportSize({ width: 1400, height: 1000 });
  const axeSrc = require('fs').readFileSync(path.join(__dirname, '..', '..', 'assets', 'vendor', 'axe-core', 'axe.min.js'), 'utf8');
  for (const tab of ['overview', 'scan', 'pages', 'issues', 'report', 'dismissed']) {
    await page.goto(adminPage + '&tab=' + tab);
    await page.evaluate(() => document.querySelectorAll('.iwt-result__toggle').forEach((b) => b.click()));
    await page.evaluate(() => document.querySelectorAll('details').forEach((d) => { d.open = true; }));
    await page.addScriptTag({ content: axeSrc });
    const v = await page.evaluate(async () => {
      const r = await window.axe.run({ include: [['.iwt-page']] }, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] } });
      return r.violations.map((x) => x.id + '(' + x.nodes.length + '): ' + x.nodes.slice(0, 2).map((n) => n.target.join(' ')).join(', '));
    });
    check('own admin UI passes axe: ' + tab, v.length === 0, v.join(' | '));
  }
  const detailHref = base + '/wp-admin/admin.php?page=iworksie-accessibility-checker&tab=pages&view=1';
  await page.goto(detailHref);
  await page.evaluate(() => { document.querySelectorAll('.iwt-result__toggle').forEach((b) => b.click()); document.querySelectorAll('details').forEach((d) => { d.open = true; }); });
  await page.addScriptTag({ content: axeSrc });
  const vd = await page.evaluate(async () => {
    const r = await window.axe.run({ include: [['.iwt-page']] }, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] } });
    return r.violations.map((x) => x.id + '(' + x.nodes.length + '): ' + x.nodes.slice(0, 2).map((n) => n.target.join(' ')).join(', '));
  });
  check('own admin UI passes axe: page detail', vd.length === 0, vd.join(' | '));

  check('no JS console errors', consoleErrors.length === 0, consoleErrors.slice(0, 5).join(' | '));
  await browser.close();
  console.log(results.join('\n'));
  const failed = results.filter((r) => r.startsWith('FAIL')).length;
  console.log(failed ? failed + ' FAILED' : 'ALL PASSED');
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.log(results.join('\n')); console.error(e); process.exit(2); });
