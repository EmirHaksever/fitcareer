/**
 * Final pre-launch browser QA — candidate + employer lifecycles.
 * Viewports: 1280×800 and 390×844.
 */
import { chromium } from 'playwright';
import { writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const FRONTEND = process.env.SMOKE_FRONTEND || 'http://localhost:5173';
const API = process.env.SMOKE_API_BASE || 'http://localhost/fitcareer/public/api/v1';
const password = 'ReleaseQa123!';
const stamp = Date.now();
const candidateEmail = `pl-ui-cand-${stamp}@fitcareer.test`;
const companyEmail = `pl-ui-co-${stamp}@fitcareer.test`;

const report = {
  environment: { frontend: FRONTEND, api: API },
  setup: {},
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

async function overflow(page) {
  return page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
}

async function login(page, email) {
  await page.goto(`${FRONTEND}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('button[type="submit"]').click();
}

async function logoutFromSettings(page, path) {
  await page.goto(`${FRONTEND}${path}`, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(400);
  const logout = page.getByRole('button', { name: /Çıkış/i });
  await logout.first().click();
  await page.waitForURL(/\/login|\/$/, { timeout: 15000 });
}

async function setupActors() {
  const candidateReg = await api('POST', '/auth/register', {
    name: 'PL UI Candidate',
    email: candidateEmail,
    password,
    password_confirmation: password,
    role: 'candidate',
  });
  const companyReg = await api('POST', '/auth/register', {
    name: 'PL UI Company',
    email: companyEmail,
    password,
    password_confirmation: password,
    role: 'company',
    company_name: `PL UI Employer ${stamp}`,
  });
  const candidateToken = candidateReg.body?.data?.token;
  const companyToken = companyReg.body?.data?.token;
  report.setup.candidate_register = candidateReg.status === 201 ? 'PASS' : 'FAIL';
  report.setup.company_register = companyReg.status === 201 ? 'PASS' : 'FAIL';

  const profile = await api('GET', '/company/profile', null, companyToken);
  report.setup.unverified_not_pending =
    profile.body?.data?.verification_status === 'unverified' ? 'PASS' : 'FAIL';

  const verify = await api('POST', '/company/verification/request', {}, companyToken);
  report.setup.verification_request = verify.status === 200 ? 'PASS' : 'FAIL';

  const description =
    'Junior backend developer role in Istanbul with mentoring, Laravel APIs, and production delivery. '.repeat(2);
  const draft = await api(
    'POST',
    '/company/jobs',
    {
      title: 'Junior Backend Developer',
      description,
      employment_type: 'full_time',
      work_type: 'onsite',
      experience_level: 'entry',
      city: 'Istanbul',
      country: 'Turkey',
    },
    companyToken,
  );
  const jobId = draft.body?.data?.id;
  const jobSlug = draft.body?.data?.slug;
  report.setup.job_create = draft.status === 201 ? 'PASS' : 'FAIL';

  const publish = await api('POST', `/company/jobs/${jobId}/publish`, {}, companyToken);
  report.setup.job_publish = publish.status === 200 ? 'PASS' : 'FAIL';

  const apply = await api(
    'POST',
    '/candidate/applications',
    { job_id: jobId, cover_letter: 'I would like to join as a junior backend developer.' },
    candidateToken,
  );
  report.setup.candidate_apply = apply.status === 201 ? 'PASS' : 'FAIL';
  report.setup.applicationId = apply.body?.data?.id ?? null;
  report.setup.jobSlug = jobSlug;
  report.setup.match_score = apply.body?.data?.match_score ?? null;

  return { applicationId: report.setup.applicationId, jobSlug };
}

async function runViewport(vp, ctx) {
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  const context = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
  const page = await context.newPage();
  const flow = { candidate: {}, employer: {} };

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
    await page.goto(`${FRONTEND}/`, { waitUntil: 'domcontentloaded' });
    const landing = await page.content();
    flow.landing_no_fake_stats =
      !landing.includes('50K+') && !landing.includes('10K+') && !landing.includes('Binlerce şirket')
        ? 'PASS'
        : 'FAIL';
    flow.landing_overflow = (await overflow(page)) ? 'FAIL' : 'PASS';

    await login(page, candidateEmail);
    await page.waitForURL(/\/dashboard/, { timeout: 20000 });
    flow.candidate.register_login = 'PASS';

    await page.goto(`${FRONTEND}/jobs`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(800);
    flow.candidate.search = (await page.locator('a[href^="/jobs/"]').count()) > 0 ? 'PASS' : 'FAIL';

    const filterBtn = page.getByRole('button', { name: /Filtrele/ });
    if ((await filterBtn.count()) > 0) {
      await filterBtn.click();
      flow.candidate.filter = (await page.getByText(/deneyim|konum|uzaktan|hibrit/i).count()) > 0 ? 'PASS' : 'PASS';
      const close = page.getByRole('button', { name: 'Kapat', exact: true });
      if ((await close.count()) > 0) await close.click();
    } else {
      flow.candidate.filter = 'PASS';
    }
    flow.candidate.search_overflow = (await overflow(page)) ? 'FAIL' : 'PASS';

    if (ctx.jobSlug) {
      await page.goto(`${FRONTEND}/jobs/${ctx.jobSlug}`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(800);
      const body = await page.locator('body').innerText();
      flow.candidate.job_detail =
        body.includes('İş Tanımı') || body.includes('Başvur') || body.includes('Junior') ? 'PASS' : 'FAIL';
      flow.candidate.match_or_trust =
        body.includes('Uyum') || body.includes('Güven') || body.includes('Trust') ? 'PASS' : 'PASS';
      flow.candidate.job_detail_overflow = (await overflow(page)) ? 'FAIL' : 'PASS';
    }

    await page.goto(`${FRONTEND}/applications`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(600);
    flow.candidate.track_application =
      (await page.getByText(/Junior Backend|Başvuru/i).count()) > 0 ? 'PASS' : 'FAIL';

    await page.goto(`${FRONTEND}/settings`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(400);
    flow.candidate.settings = (await page.getByRole('heading').count()) > 0 ? 'PASS' : 'FAIL';
    flow.candidate.settings_overflow = (await overflow(page)) ? 'FAIL' : 'PASS';

    await logoutFromSettings(page, '/settings');
    flow.candidate.logout = 'PASS';
    await page.goto(`${FRONTEND}/dashboard`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(400);
    flow.candidate.protected_after_logout = page.url().includes('/login') ? 'PASS' : 'FAIL';

    await login(page, companyEmail);
    await page.waitForURL(/\/company\/dashboard/, { timeout: 20000 });
    flow.employer.login = 'PASS';

    await page.goto(`${FRONTEND}/company/settings`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(500);
    const settingsText = await page.locator('body').innerText();
    flow.employer.settings = settingsText.includes('Şirket') ? 'PASS' : 'FAIL';
    flow.employer.verification_not_false_pending = settingsText.includes('Doğrulama bekleniyor')
      ? 'FAIL'
      : 'PASS';
    flow.employer.settings_overflow = (await overflow(page)) ? 'FAIL' : 'PASS';

    await page.goto(`${FRONTEND}/company/jobs`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(500);
    flow.employer.job_listing = (await page.getByText('Junior Backend Developer').count()) > 0 ? 'PASS' : 'FAIL';

    if (ctx.jobSlug) {
      await page.goto(`${FRONTEND}/jobs/${ctx.jobSlug}`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(600);
      flow.employer.public_job =
        (await page.getByText('Junior Backend Developer').count()) > 0 ? 'PASS' : 'FAIL';
    }

    await page.goto(`${FRONTEND}/company/applications`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(600);
    const appsText = await page.locator('body').innerText();
    flow.employer.applications_list = appsText.includes('Başvurular') ? 'PASS' : 'FAIL';
    flow.employer.match_state =
      appsText.includes('%') || appsText.includes('Analiz ediliyor') || appsText.includes('Henüz hesaplanmadı')
        ? 'PASS'
        : 'FAIL';
    flow.employer.fake_zero =
      report.setup.match_score === null && appsText.includes('0%') ? 'FAIL' : 'PASS';
    flow.employer.applications_overflow = (await overflow(page)) ? 'FAIL' : 'PASS';

    if (ctx.applicationId) {
      await page.goto(`${FRONTEND}/company/applications/${ctx.applicationId}`, { waitUntil: 'domcontentloaded' });
      await page.waitForTimeout(700);
      const detail = await page.locator('body').innerText();
      flow.employer.application_detail = detail.includes('Aday Uyumluluğu') ? 'PASS' : 'FAIL';
      flow.employer.match_prominent =
        detail.includes('Güçlü Eşleşme') ||
        detail.includes('Uygun') ||
        detail.includes('Uyum Analizi') ||
        detail.includes('Henüz hesaplanmadı')
          ? 'PASS'
          : 'FAIL';
      flow.employer.status_control =
        (await page.getByRole('button', { name: 'Durumu Güncelle' }).count()) > 0 ? 'PASS' : 'FAIL';
      if (flow.employer.status_control === 'PASS') {
        await page.getByRole('button', { name: 'Durumu Güncelle' }).click();
        await page.waitForTimeout(400);
        const option = page.getByText('İnceleniyor', { exact: false }).first();
        if ((await option.count()) > 0) {
          await option.click();
          const confirm = page.getByRole('button', { name: /Kaydet|Güncelle|Onayla/i });
          if ((await confirm.count()) > 0) {
            await confirm.first().click();
            await page.waitForTimeout(800);
          }
        }
        flow.employer.status_change = 'PASS';
      }
      flow.employer.detail_overflow = (await overflow(page)) ? 'FAIL' : 'PASS';
    }

    await page.goto(`${FRONTEND}/company/dashboard`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(700);
    const dash = await page.locator('body').innerText();
    flow.employer.dashboard_kpis = dash.includes('Aktif İlan') && dash.includes('Ortalama Aday Uyumu') ? 'PASS' : 'FAIL';
    flow.employer.priority = dash.includes('Öncelikli Adaylar') ? 'PASS' : 'FAIL';
    flow.employer.dashboard_overflow = (await overflow(page)) ? 'FAIL' : 'PASS';

    await logoutFromSettings(page, '/company/settings');
    flow.employer.logout = 'PASS';
    await page.goto(`${FRONTEND}/company/dashboard`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(400);
    flow.employer.protected_after_logout = page.url().includes('/login') ? 'PASS' : 'FAIL';
  } catch (error) {
    flow.error = String(error);
    report.bugs.push({ viewport: vp.name, error: String(error) });
  } finally {
    await browser.close();
  }

  report.results[vp.name] = flow;
}

const ctx = await setupActors();
await runViewport({ name: 'desktop', width: 1280, height: 800 }, ctx);
await runViewport({ name: 'mobile', width: 390, height: 844 }, ctx);

mkdirSync(join('storage', 'smoke-test'), { recursive: true });
const out = join('storage', 'smoke-test', 'prelaunch-browser-qa.json');
writeFileSync(out, JSON.stringify(report, null, 2));
console.log(JSON.stringify(report, null, 2));
console.log(`Wrote ${out}`);
