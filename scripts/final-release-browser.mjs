/**
 * Final release browser smoke — Playwright headless.
 * Viewports: 390×844 and 1280×800.
 */
import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const FRONTEND = process.env.SMOKE_FRONTEND || 'http://localhost:5173';
const API = process.env.SMOKE_API_BASE || 'http://127.0.0.1:8000/api/v1';
const password = 'ReleaseQa123!';
const stamp = Date.now();
const candidateEmail = process.env.SMOKE_CANDIDATE_EMAIL || `rc-ui-cand-${stamp}@fitcareer.test`;
const companyEmail = process.env.SMOKE_COMPANY_EMAIL || `rc-ui-co-${stamp}@fitcareer.test`;
const skipRegister = Boolean(process.env.SMOKE_CANDIDATE_EMAIL && process.env.SMOKE_COMPANY_EMAIL);

const viewports = [
  { name: 'mobile', width: 390, height: 844 },
  { name: 'desktop', width: 1280, height: 800 },
];

const report = {
  environment: { frontend: FRONTEND, api: API },
  results: {},
  consoleErrors: [],
  networkFailed: [],
  bugs: [],
};

async function api(method, path, body, token) {
  const res = await fetch(`${API}${path}`, {
    method,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await res.json().catch(() => null);
  return { status: res.status, body: json };
}

function has(page, text) {
  return page.getByText(text, { exact: false }).count();
}

async function overflow(page) {
  return page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
}

async function login(page, email) {
  await page.goto(`${FRONTEND}/login`, { waitUntil: 'networkidle' });
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('button[type="submit"]').click();
}

async function runViewport(vp) {
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  const context = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
  const page = await context.newPage();
  const flow = {};

  page.on('console', (msg) => {
    if (msg.type() === 'error') {
      report.consoleErrors.push({ viewport: vp.name, text: msg.text() });
    }
  });
  page.on('response', (res) => {
    if (res.url().includes('/api/') && res.status() >= 500) {
      report.networkFailed.push({ viewport: vp.name, url: res.url(), status: res.status() });
    }
  });

  try {
    await page.goto(`${FRONTEND}/`, { waitUntil: 'networkidle' });
    const landing = await page.content();
    flow.landing_no_fake_stats =
      !landing.includes('50K+') && !landing.includes('10K+') && !landing.includes('Binlerce şirket')
        ? 'PASS'
        : 'FAIL';
    flow.landing_overflow = (await overflow(page)) ? 'FAIL' : 'PASS';

    await login(page, candidateEmail);
    await page.waitForURL(/\/dashboard/, { timeout: 20000 });
    flow.candidate_login = 'PASS';
    await page.getByText('Güvenilir İlanlar', { exact: true }).waitFor({ timeout: 15000 });
    flow.candidate_dashboard = 'PASS';
    flow.candidate_dashboard_overflow = (await overflow(page)) ? 'FAIL' : 'PASS';

    await page.goto(`${FRONTEND}/jobs`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    const jobLinks = page.locator('a[href^="/jobs/"]');
    flow.job_list = (await jobLinks.count()) > 0 ? 'PASS' : 'FAIL';

    await page.locator('input[name="keyword"]').fill('frontend');
    await page.locator('button[aria-label="Ara"]').click();
    await page.waitForTimeout(1000);
    flow.turkish_or_synonym_search = page.url().includes('keyword=frontend') ? 'PASS' : 'FAIL';

    await page.getByRole('button', { name: /Filtrele/ }).click();
    flow.filter_drawer = (await has(page, 'En düşük güven skoru')) > 0 ? 'PASS' : 'FAIL';
    await page.getByRole('button', { name: 'Kapat', exact: true }).click();

    if ((await jobLinks.count()) > 0) {
      await jobLinks.first().click();
      await page.waitForURL(/\/jobs\/[^/?]+/, { timeout: 15000 });
      const detailHeading = page.getByRole('heading', { name: 'İş Tanımı' });
      const missing = page.getByText('İlan yüklenemedi');
      await Promise.race([
        detailHeading.waitFor({ timeout: 15000 }),
        missing.waitFor({ timeout: 15000 }),
      ]).catch(() => null);
      flow.job_detail = (await detailHeading.count()) > 0 ? 'PASS' : 'FAIL';
      flow.job_detail_overflow = (await overflow(page)) ? 'FAIL' : 'PASS';
    }

    await page.goto(`${FRONTEND}/notifications`, { waitUntil: 'networkidle' });
    flow.notifications = (await page.locator('h1').count()) > 0 ? 'PASS' : 'FAIL';

    await page.goto(`${FRONTEND}/settings`, { waitUntil: 'networkidle' });
    flow.candidate_settings = (await has(page, 'Şifre')) > 0 ? 'PASS' : 'FAIL';
    await page.getByRole('button', { name: 'Çıkış yap' }).click();
    await page.waitForURL(/\/login/, { timeout: 15000 });
    flow.candidate_logout = 'PASS';
    await login(page, companyEmail);
    await page.waitForURL(/\/company\/dashboard/, { timeout: 20000 });
    flow.company_login = 'PASS';

    await page.goto(`${FRONTEND}/company/settings`, { waitUntil: 'networkidle' });
    const unverifiedCopy = (await has(page, 'Şirket doğrulaması henüz başlatılmadı')) > 0;
    const pendingCopy = (await has(page, 'Doğrulama talebiniz inceleniyor')) > 0;
    const verifiedCopy = (await has(page, 'Şirketiniz doğrulandı')) > 0;
    flow.company_settings_verification =
      unverifiedCopy || pendingCopy || verifiedCopy ? 'PASS' : 'FAIL';
    flow.unverified_not_shown_as_pending = pendingCopy && unverifiedCopy ? 'FAIL' : 'PASS';
    flow.company_settings_overflow = (await overflow(page)) ? 'FAIL' : 'PASS';
    flow.company_logout_control = (await has(page, 'Çıkış yap')) > 0 ? 'PASS' : 'FAIL';

    await page.goto(`${FRONTEND}/company/jobs/new`, { waitUntil: 'networkidle' });
    flow.company_job_form = (await page.getByRole('heading', { name: 'İlan Oluştur' }).count()) > 0 ? 'PASS' : 'FAIL';
    flow.company_job_form_overflow = (await overflow(page)) ? 'FAIL' : 'PASS';

    await page.goto(`${FRONTEND}/company/jobs`, { waitUntil: 'networkidle' });
    flow.company_jobs = (await page.locator('h1').count()) > 0 ? 'PASS' : 'FAIL';
    flow.company_jobs_overflow = (await overflow(page)) ? 'FAIL' : 'PASS';

    await page.goto(`${FRONTEND}/company/settings`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Çıkış yap' }).click();
    await page.waitForURL(/\/login/, { timeout: 15000 });
    flow.company_logout = 'PASS';
    await page.goto(`${FRONTEND}/company/dashboard`, { waitUntil: 'networkidle' });
    flow.company_protected_after_logout = page.url().includes('/login') ? 'PASS' : 'FAIL';
  } catch (err) {
    flow.error = String(err);
    report.bugs.push({ viewport: vp.name, error: String(err) });
  }

  report.results[vp.name] = flow;
  await browser.close();
}

if (!skipRegister) {
  const candidate = await api('POST', '/auth/register', {
    name: `RC UI Candidate ${stamp}`,
    email: candidateEmail,
    password,
    password_confirmation: password,
    role: 'candidate',
  });
  const company = await api('POST', '/auth/register', {
    name: `RC UI Company ${stamp}`,
    email: companyEmail,
    password,
    password_confirmation: password,
    role: 'company',
    company_name: `RC UI Employer ${stamp}`,
  });

  if (candidate.status !== 201 || company.status !== 201) {
    console.error({ candidate, company });
    process.exit(1);
  }

  report.actors = { candidateEmail, companyEmail, companyId: company.body?.data?.user?.id };
} else {
  report.actors = { candidateEmail, companyEmail, reused: true };
}

for (const vp of viewports) {
  await runViewport(vp);
}

const outDir = join(process.cwd(), 'storage', 'smoke-test');
mkdirSync(outDir, { recursive: true });
writeFileSync(join(outDir, 'final-release-browser.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));

const failed = Object.values(report.results).some((flow) =>
  Object.entries(flow).some(([key, value]) => key !== 'error' && value === 'FAIL'),
);
if (failed || report.bugs.length > 0 || report.networkFailed.length > 0) {
  process.exit(1);
}
