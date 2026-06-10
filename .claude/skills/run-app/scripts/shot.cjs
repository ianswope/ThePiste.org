// Log in to the local ThePiste dev server and screenshot a page.
//
//   node shot.cjs <path> <output.png> [actions.cjs]
//
//   path        e.g. /season/budget
//   output.png  where the full-page screenshot lands
//   actions.cjs optional module: module.exports = async (page) => { ... }
//               run after login + navigation, before the screenshot.
//
// Must be run from a directory where `require('playwright')` resolves
// (see SKILL.md for the temp-install recipe).

const { chromium } = require('playwright');

const BASE = process.env.PISTE_URL || 'http://127.0.0.1:8010';
const [, , path = '/', out = '/tmp/piste.png', actionsFile] = process.argv;

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
  const errors = [];
  page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));

  await page.goto(`${BASE}/login`);
  await page.fill('input[type=email], input[name=email]', 'ian@promoeqp.com');
  await page.fill('input[type=password], input[name=password]', 'changeme-piste');
  await page.click('button[type=submit]');
  await page.waitForLoadState('networkidle');

  await page.goto(`${BASE}${path}`);
  await page.waitForLoadState('networkidle');

  if (actionsFile) {
    await require(require('node:path').resolve(actionsFile))(page);
  }

  await page.screenshot({ path: out, fullPage: true });
  console.log(`screenshot: ${out}`);
  console.log('console errors:', errors.length ? errors : 'none');
  await browser.close();
  process.exit(errors.length ? 2 : 0);
})();
