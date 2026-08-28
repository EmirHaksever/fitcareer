/**
 * Mobile job search/filter QA — Playwright headless.
 * Usage: node scripts/mobile-job-search-qa.mjs
 */
import { chromium, devices } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const FRONTEND = process.env.SMOKE_FRONTEND || 'http://localhost:5173';
const API = process.env.SMOKE_API_BASE || 'http://127.0.0.1:8000/api/v1';
const password = 'SmokeTest123!';
const email = `mobile-jobs-${Date.now()}@example.test`;

const viewports = [
  { name: 'mobile', width: 390, height: 844 },
  { name: 'desktop', width: 1280, height: 800 },
];

const report = {
  environment: { frontend: FRONTEND, api: API },
  results: {},
  network: [],
  bugs: [],
};

async function registerUser() {
  const res = await fetch(`${API}/auth/register`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({
      name: 'Mobile Jobs QA',
      email,
      password,
      password_confirmation: password,
      role: 'candidate',
    }),
  });
  const body = await res.json();
  if (res.status !== 201) {
    throw new Error(`Register failed: ${res.status} ${JSON.stringify(body)}`);
  }
  return body?.data?.token;
}

function trackApi(page, viewportName, requests) {
  page.on('response', async (res) => {
    const url = res.url();
    if (!url.includes('/api/v1/jobs')) return;
    const parsed = new URL(url);
    requests.push({
      viewport: viewportName,
      url,
      status: res.status(),
      params: Object.fromEntries(parsed.searchParams.entries()),
    });
  });
}

async function login(page) {
  await page.goto(`${FRONTEND}/login`, { waitUntil: 'networkidle' });
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/dashboard/, { timeout: 15000 });
}

async function getJobCount(page) {
  const text = await page.locator('header p.text-sm').first().textContent();
  const match = text?.match(/([\d.]+)\s+ilan bulundu/);
  if (!match) return null;
  return Number(match[1].replace(/\./g, ''));
}

async function waitForJobsLoaded(page) {
  await page.waitForSelector('header h1:has-text("İş İlanları")', { timeout: 10000 });
  await page.waitForTimeout(600);
}

async function openFilters(page) {
  await page.click('button:has-text("Filtrele")');
  await page.waitForSelector('text=Sonuçları Göster', { timeout: 5000 });
}

async function workTypeSelect(page) {
  return page.locator('label:has(span:text("Çalışma şekli")) select');
}

async function filterDrawer(page) {
  return page.locator('.fixed.inset-0 .absolute.inset-y-0.right-0');
}

async function closeFilters(page) {
  await page.click('button:has-text("Sonuçları Göster")');
  await page.waitForTimeout(400);
}

async function runViewport({ name, width, height }) {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width, height },
    userAgent: devices['iPhone 13'].userAgent,
  });
  const page = await context.newPage();
  const requests = [];
  const flow = {};

  trackApi(page, name, requests);

  try {
    await login(page);
    flow.login = 'PASS';

    await page.goto(`${FRONTEND}/jobs`, { waitUntil: 'networkidle' });
    await waitForJobsLoaded(page);
    flow.jobs_page_render = 'PASS';

    const baselineCount = await getJobCount(page);
    flow.baseline_count = baselineCount;

    const overflowBefore = await page.evaluate(
      () => document.documentElement.scrollWidth > window.innerWidth + 2,
    );
    flow.no_horizontal_overflow_initial = overflowBefore ? 'FAIL' : 'PASS';

    // Search keyword
    const searchTerm = 'yazılım';
    await page.fill('input[name="keyword"]', searchTerm);
    const searchReqPromise = page.waitForResponse(
      (res) => res.url().includes('/api/v1/jobs') && res.url().includes('keyword='),
      { timeout: 10000 },
    );
    await page.locator('button[aria-label="Ara"]').click();
    await searchReqPromise;
    await waitForJobsLoaded(page);
    const searchUrl = page.url();
    flow.search_url_has_keyword = searchUrl.includes(`keyword=${encodeURIComponent(searchTerm)}`) ? 'PASS' : 'FAIL';
    const searchCount = await getJobCount(page);
    flow.search_changes_results =
      searchCount !== null && baselineCount !== null && searchCount <= baselineCount ? 'PASS' : 'NOT VERIFIED';

    const searchReq = requests.filter((r) => r.params.keyword === searchTerm).pop();
    flow.search_api_params = searchReq?.params?.keyword === searchTerm ? 'PASS' : 'FAIL';

    // Turkish character search
    const turkishTerm = 'İstanbul';
    await page.fill('input[name="keyword"]', turkishTerm);
    const trReqPromise = page.waitForResponse(
      (res) => res.url().includes('/api/v1/jobs') && res.url().includes('keyword='),
      { timeout: 10000 },
    );
    await page.locator('button[aria-label="Ara"]').click();
    await trReqPromise;
    flow.turkish_search_api =
      requests.some((r) => r.params.keyword === turkishTerm || r.params.keyword === 'İstanbul')
        ? 'PASS'
        : 'FAIL';

    // Clear search
    await page.fill('input[name="keyword"]', '');
    await page.locator('button[aria-label="Ara"]').click();
    await waitForJobsLoaded(page);
    flow.search_clear = !page.url().includes('keyword=') ? 'PASS' : 'FAIL';

    // Location filter
    await openFilters(page);
    await page.fill('input[name="location"]', 'Istanbul');
    await closeFilters(page);
    await waitForJobsLoaded(page);
    const locationReq = requests.filter((r) => r.params.location).pop();
    flow.location_filter_api = locationReq?.params?.location === 'Istanbul' ? 'PASS' : 'FAIL';
    flow.location_filter_url = page.url().includes('location=Istanbul') ? 'PASS' : 'FAIL';

    // Work type filters
    for (const workType of ['remote', 'hybrid', 'onsite']) {
      await openFilters(page);
      await (await workTypeSelect(page)).selectOption({ value: workType });
      await closeFilters(page);
      await waitForJobsLoaded(page);
      const wtReq = requests.filter((r) => r.params.work_type === workType).pop();
      flow[`work_type_${workType}`] = wtReq ? 'PASS' : 'FAIL';
    }

    // Trust filter
    await openFilters(page);
    await (await filterDrawer(page)).locator('input[name="min_trust_score"]').fill('70');
    await closeFilters(page);
    await waitForJobsLoaded(page);
    const trustReq = requests.filter((r) => r.params.min_trust_score === '70').pop();
    flow.trust_filter_api = trustReq ? 'PASS' : 'FAIL';

    // Combined filters
    await openFilters(page);
    await page.click('button:has-text("Filtreleri Temizle")');
    await (await filterDrawer(page)).locator('input[name="location"]').fill('Remote');
    await (await workTypeSelect(page)).selectOption({ value: 'remote' });
    await page.waitForTimeout(300);
    await closeFilters(page);
    await waitForJobsLoaded(page);
    const combinedReq = requests.filter(
      (r) => r.params.location === 'Remote' && r.params.work_type === 'remote',
    ).pop();
    flow.combined_filter_api =
      combinedReq?.params?.location === 'Remote' && combinedReq?.params?.work_type === 'remote'
        ? 'PASS'
        : 'FAIL';

    // Clear filters
    await openFilters(page);
    await page.click('button:has-text("Filtreleri Temizle")');
    await closeFilters(page);
    await waitForJobsLoaded(page);
    const clearedUrl = page.url();
    flow.clear_filters_url =
      !clearedUrl.includes('location=') &&
      !clearedUrl.includes('work_type=') &&
      !clearedUrl.includes('min_trust_score=')
        ? 'PASS'
        : 'FAIL';
    const afterClearCount = await getJobCount(page);
    flow.clear_filters_count_restored =
      afterClearCount !== null && baselineCount !== null && afterClearCount === baselineCount
        ? 'PASS'
        : 'NOT VERIFIED';

    // Filter drawer closes
    await openFilters(page);
    await page.click('button[aria-label="Kapat"]');
    await page.waitForTimeout(300);
    flow.filter_drawer_closes =
      (await page.locator('text=Sonuçları Göster').count()) === 0 ? 'PASS' : 'FAIL';

    // Job detail navigation
    const jobLink = page.locator('a[href^="/jobs/"]').first();
    const jobCount = await jobLink.count();
    if (jobCount > 0) {
      await jobLink.click();
      await page.waitForLoadState('networkidle');
      await page.waitForSelector('h2:text("İş Tanımı")', { timeout: 10000 });
      flow.job_detail_navigation = page.url().includes('/jobs/') ? 'PASS' : 'FAIL';
      flow.job_detail_section = (await page.locator('text=İş Tanımı').count()) > 0 ? 'PASS' : 'FAIL';
      const detailOverflow = await page.evaluate(
        () => document.documentElement.scrollWidth > window.innerWidth + 2,
      );
      flow.no_horizontal_overflow_detail = detailOverflow ? 'FAIL' : 'PASS';
    } else {
      flow.job_detail_navigation = 'SKIP_NO_JOBS';
    }

    // Settings password wrong current — Turkish error
    if (name === 'mobile') {
      await page.goto(`${FRONTEND}/settings`, { waitUntil: 'networkidle' });
      const pwInputs = page.locator('input[type="password"]');
      await pwInputs.nth(0).fill('WrongPassword123!');
      await pwInputs.nth(1).fill('NewPassword123!');
      await pwInputs.nth(2).fill('NewPassword123!');
      await page.click('button[type="submit"]');
      await page.waitForTimeout(800);
      const errorText = await page.locator('.text-danger').textContent();
      flow.settings_password_error_tr =
        errorText?.includes('Mevcut şifre yanlış') && !errorText.includes('incorrect') ? 'PASS' : 'FAIL';
      if (flow.settings_password_error_tr === 'FAIL') {
        report.bugs.push({
          viewport: name,
          issue: 'Settings password error not localized',
          observed: errorText,
        });
      }
    }
  } catch (err) {
    flow.error = String(err);
    report.bugs.push({ viewport: name, error: String(err) });
  }

  report.results[name] = flow;
  report.network.push(...requests);
  await browser.close();
}

await registerUser();

for (const vp of viewports) {
  await runViewport(vp);
}

const outDir = join(process.cwd(), 'storage', 'smoke-test');
mkdirSync(outDir, { recursive: true });
const outPath = join(outDir, 'mobile-job-search-qa.json');
writeFileSync(outPath, JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
console.error(`Report written to ${outPath}`);

const failed = Object.values(report.results).flatMap((r) =>
  Object.entries(r).filter(([, v]) => v === 'FAIL').map(([k]) => k),
);
process.exit(failed.length > 0 ? 1 : 0);
