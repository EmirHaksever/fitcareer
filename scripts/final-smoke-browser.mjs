/**
 * Browser smoke test — Playwright headless.
 * Usage: node scripts/final-smoke-browser.mjs
 * Requires: npx playwright (auto-installed on first run)
 */
import { chromium, devices } from 'playwright';
import { writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const FRONTEND = process.env.SMOKE_FRONTEND || 'http://localhost:5173';
const API = process.env.SMOKE_API_BASE || 'http://127.0.0.1:8000/api/v1';
const password = 'SmokeTest123!';
const email = `smoke-ui-${Date.now()}@example.test`;

const viewports = [
  { name: 'mobile', ...devices['iPhone 13'] },
  { name: 'desktop', viewport: { width: 1280, height: 800 } },
];

const report = {
  environment: { frontend: FRONTEND, api: API, browser: 'chromium-headless' },
  results: {},
  console: { errors: [], warnings: [] },
  network: { failed: [], duplicates: [] },
  bugs: [],
};

async function apiRegister() {
  const res = await fetch(`${API}/auth/register`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({
      name: 'Smoke UI Tester',
      email,
      password,
      password_confirmation: password,
      role: 'candidate',
    }),
  });
  const body = await res.json();
  return { status: res.status, token: body?.data?.token, body };
}

async function runViewport(config) {
  const browser = await chromium.launch({ headless: true });
  const contextOptions =
    config.name === 'mobile'
      ? { ...devices['iPhone 13'] }
      : { viewport: { width: 1280, height: 800 } };
  const context = await browser.newContext(contextOptions);
  const page = await context.newPage();
  const vp = config.name;
  const flow = {};
  const requests = [];

  page.on('console', (msg) => {
    const type = msg.type();
    const text = msg.text();
    if (type === 'error') report.console.errors.push({ viewport: vp, text });
    if (type === 'warning') report.console.warnings.push({ viewport: vp, text });
  });

  page.on('response', (res) => {
    const url = res.url();
    if (url.includes('/api/')) {
      requests.push({ url, status: res.status() });
      if (res.status() >= 400) {
        report.network.failed.push({ viewport: vp, url, status: res.status() });
      }
    }
  });

  try {
    // Login page
    await page.goto(`${FRONTEND}/login`, { waitUntil: 'networkidle' });
    flow.login_render = (await page.locator('h2').filter({ hasText: 'Giriş Yap' }).count()) > 0 ? 'PASS' : 'FAIL';

    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', 'WrongPassword!');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(800);
    const formErrorVisible = await page.locator('text=Giriş başarısız').or(page.locator('.text-danger')).count();
    flow.login_wrong_password = formErrorVisible > 0 ? 'PASS' : 'NOT VERIFIED';

    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/dashboard/, { timeout: 15000 });
    flow.login_success = page.url().includes('/dashboard') ? 'PASS' : 'FAIL';

    // Dashboard
    await page.waitForSelector('text=Güvenilir İlanlar', { timeout: 10000 });
    flow.dashboard_render = 'PASS';

    const trustedText = await page.locator('text=Güvenilir İlanlar').locator('..').locator('..').textContent();
    flow.dashboard_has_numbers = /\d+/.test(trustedText || '') ? 'PASS' : 'FAIL';

    const chart = await page.locator('text=Piyasa Güvenilirlik Dağılımı').count();
    flow.dashboard_chart = chart > 0 ? 'PASS' : 'FAIL';

    // Job list
    await page.goto(`${FRONTEND}/jobs`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
    const jobLinks = await page.locator('a[href^="/jobs/"]').count();
    flow.job_list = jobLinks > 0 ? 'PASS' : 'FAIL';

    // Job detail — first job
    if (jobLinks > 0) {
      await page.locator('a[href^="/jobs/"]').first().click();
      await page.waitForLoadState('networkidle');
      flow.job_detail = (await page.locator('text=İş Tanımı').count()) > 0 ? 'PASS' : 'FAIL';
      flow.trust_tab = (await page.locator('button', { hasText: 'Güvenilirlik' }).count()) > 0 ? 'PASS' : 'NOT VERIFIED';
    }

    // Saved
    await page.goto(`${FRONTEND}/saved`, { waitUntil: 'networkidle' });
    flow.saved_page = (await page.locator('h1').filter({ hasText: 'Kaydedilen' }).count()) > 0 ? 'PASS' : 'FAIL';

    // Fit analysis
    await page.goto(`${FRONTEND}/fit-analysis`, { waitUntil: 'networkidle' });
    const hasTodo = (await page.locator('text=TODO').count()) + (await page.locator('text=endpoint bekleniyor').count());
    flow.fit_analysis = hasTodo === 0 ? 'PASS' : 'FAIL';

    // Notifications
    await page.goto(`${FRONTEND}/notifications`, { waitUntil: 'networkidle' });
    flow.notifications = (await page.locator('text=Henüz bildirim yok').count()) > 0 ? 'PASS' : 'FAIL';

    // Settings render (skip password change in UI — covered by API smoke)
    await page.goto(`${FRONTEND}/settings`, { waitUntil: 'networkidle' });
    flow.settings = (await page.locator('text=Şifre Değiştir').count()) > 0 ? 'PASS' : 'FAIL';

    // Score alignment check on dashboard recommendations
    await page.goto(`${FRONTEND}/dashboard`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(500);
    const scoreRow = page.locator('text=Uyum Skoru').first();
    flow.score_labels_visible = (await scoreRow.count()) > 0 ? 'PASS' : 'NOT VERIFIED';

    // Horizontal overflow
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
    flow.no_horizontal_overflow = overflow ? 'FAIL' : 'PASS';
  } catch (err) {
    flow.error = String(err);
    report.bugs.push({ viewport: vp, error: String(err) });
  }

  report.results[vp] = flow;
  await browser.close();
}

const reg = await apiRegister();
if (reg.status !== 201) {
  console.error('Register failed', reg);
  process.exit(1);
}

for (const vp of viewports) {
  await runViewport(vp);
}

const outDir = join(process.cwd(), 'storage', 'smoke-test');
mkdirSync(outDir, { recursive: true });
writeFileSync(join(outDir, 'browser-report.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
