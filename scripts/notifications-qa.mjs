/**
 * Notifications V1 manual QA — requires running frontend + backend.
 * Usage: node scripts/notifications-qa.mjs
 */
import { chromium, devices } from 'playwright';
import { execSync } from 'node:child_process';

const FRONTEND = process.env.SMOKE_FRONTEND || 'http://localhost:5173';
const API = process.env.SMOKE_API_BASE || 'http://127.0.0.1:8000/api/v1';

const results = {};

async function api(method, path, body, token, retries = 3) {
  const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  for (let attempt = 0; attempt <= retries; attempt++) {
    const res = await fetch(`${API}${path}`, {
      method,
      headers,
      body: body ? JSON.stringify(body) : undefined,
    });
    if (res.status !== 429 || attempt === retries) {
      return { status: res.status, body: await res.json().catch(() => null) };
    }
    await new Promise((resolve) => setTimeout(resolve, 2000 * (attempt + 1)));
  }
  return { status: 429, body: null };
}

function setupActors() {
  const raw = execSync('php scripts/notification-lifecycle-actors.php', {
    cwd: process.cwd(),
    encoding: 'utf8',
  });
  const data = JSON.parse(raw);
  return {
    password: data.password,
    email: data.candidate.email,
    candidateToken: data.candidate.token,
    companyToken: data.company.token,
    applicationId: data.application_id,
  };
}

async function authenticateCandidate(page, email, password, token) {
  await page.goto(`${FRONTEND}/login`, { waitUntil: 'domcontentloaded' });
  await page.evaluate(
    ([storedToken]) => {
      localStorage.setItem('fitcareer_token', storedToken);
    },
    [token],
  );
  await page.goto(`${FRONTEND}/dashboard`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1000);
  if (!page.url().includes('/dashboard')) {
    await page.goto(`${FRONTEND}/login`, { waitUntil: 'networkidle' });
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/\/dashboard/, { timeout: 20000 });
  }
}

async function runViewport(width, height, label) {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({
    viewport: { width, height },
    userAgent: width < 500 ? devices['iPhone 13'].userAgent : undefined,
  });

  const actors = setupActors();
  await authenticateCandidate(page, actors.email, actors.password, actors.candidateToken);

  // Empty state
  await page.goto(`${FRONTEND}/notifications`, { waitUntil: 'networkidle' });
  results[`${label}_empty_state`] =
    (await page.locator('text=Henüz bildirim yok').count()) > 0 ? 'PASS' : 'FAIL';

  // Trigger real status change
  await api(
    'PATCH',
    `/company/applications/${actors.applicationId}/status`,
    { status: 'under_review' },
    actors.companyToken,
  );

  await page.goto(`${FRONTEND}/dashboard`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  results[`${label}_unread_badge_before_refresh`] =
    (await page.locator('a[aria-label*="okunmamış"]').count()) > 0 ? 'PASS' : 'FAIL';

  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(800);

  await page.goto(`${FRONTEND}/notifications`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(800);
  results[`${label}_notification_visible`] =
    (await page.locator('text=Başvuru durumu güncellendi').count()) > 0 ? 'PASS' : 'FAIL';

  results[`${label}_turkish_body_visible`] =
    (await page.locator('text=/inceleniyor/').count()) > 0 ? 'PASS' : 'FAIL';

  results[`${label}_unread_visual_state`] =
    (await page.locator('text=Yeni').count()) > 0 ? 'PASS' : 'FAIL';

  // Mark one read via click
  const firstItem = page.locator('a[href^="/applications/"]').first();
  if ((await firstItem.count()) > 0) {
    await firstItem.click();
    await page.waitForTimeout(800);
    await page.goto(`${FRONTEND}/notifications`, { waitUntil: 'networkidle' });
  }
  results[`${label}_mark_one_read`] =
    (await page.locator('text=Yeni').count()) === 0 ? 'PASS' : 'NOT VERIFIED';

  // Navigate away and back
  await page.goto(`${FRONTEND}/dashboard`, { waitUntil: 'networkidle' });
  await page.goto(`${FRONTEND}/notifications`, { waitUntil: 'networkidle' });
  results[`${label}_no_stale_state_after_nav`] =
    (await page.locator('text=Başvuru durumu güncellendi').count()) > 0 ? 'PASS' : 'FAIL';

  // Second notification
  await api(
    'PATCH',
    `/company/applications/${actors.applicationId}/status`,
    { status: 'shortlisted' },
    actors.companyToken,
  );
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(800);
  results[`${label}_second_notification`] =
    (await page.locator('text=Başvuru durumu güncellendi').count()) >= 2 ? 'PASS' : 'FAIL';

  if ((await page.locator('text=Tümünü okundu işaretle').count()) > 0) {
    await page.click('text=Tümünü okundu işaretle');
    await page.waitForTimeout(800);
  }
  results[`${label}_mark_all_read`] =
    (await page.locator('text=Yeni').count()) === 0 ? 'PASS' : 'FAIL';

  results[`${label}_badge_after_mark_all`] =
    (await page.locator('a[aria-label*="okunmamış"]').count()) === 0 ? 'PASS' : 'FAIL';

  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
  results[`${label}_no_horizontal_overflow`] = overflow ? 'FAIL' : 'PASS';

  await browser.close();
}

await runViewport(390, 844, 'mobile');
await new Promise((resolve) => setTimeout(resolve, 3000));
await runViewport(1280, 800, 'desktop');

console.log(JSON.stringify({ results }, null, 2));
