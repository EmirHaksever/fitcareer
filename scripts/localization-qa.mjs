/**
 * Localization QA — auth errors + filter labels.
 * Usage: node scripts/localization-qa.mjs
 */
import { chromium, devices } from 'playwright';

const FRONTEND = process.env.SMOKE_FRONTEND || 'http://localhost:5173';
const API = process.env.SMOKE_API_BASE || 'http://127.0.0.1:8000/api/v1';

const results = {};

async function run() {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({
    viewport: { width: 390, height: 844 },
    userAgent: devices['iPhone 13'].userAgent,
  });

  // Login validation
  await page.goto(`${FRONTEND}/login`, { waitUntil: 'networkidle' });
  await page.click('button[type="submit"]');
  await page.waitForTimeout(500);
  const loginEmailError = await page.locator('#email-error, [name="email"] ~ p.text-danger, .text-danger').first().textContent().catch(() => '');
  results.login_validation_tr =
    loginEmailError?.includes('E-posta') && !loginEmailError?.includes('required') ? 'PASS' : 'NOT VERIFIED';

  // Login invalid credentials
  await page.fill('input[name="email"]', 'missing@example.test');
  await page.fill('input[name="password"]', 'WrongPass123!');
  await page.click('button[type="submit"]');
  await page.waitForTimeout(800);
  const loginFormError = await page.locator('.text-danger').last().textContent();
  results.login_invalid_credentials_tr =
    loginFormError?.includes('hatalı') && !loginFormError?.includes('Invalid') ? 'PASS' : 'FAIL';

  // Register validation
  await page.goto(`${FRONTEND}/register`, { waitUntil: 'networkidle' });
  await page.click('button[type="submit"]');
  await page.waitForTimeout(500);
  const registerErrors = await page.locator('.text-danger').allTextContents();
  results.register_validation_tr =
    registerErrors.some((t) => t.includes('zorunlu')) && !registerErrors.some((t) => /required|Validation failed/i.test(t))
      ? 'PASS'
      : 'NOT VERIFIED';

  // Filter labels — need login first
  const email = `loc-qa-${Date.now()}@example.test`;
  const password = 'SmokeTest123!';
  await fetch(`${API}/auth/register`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify({
      name: 'Loc QA',
      email,
      password,
      password_confirmation: password,
      role: 'candidate',
    }),
  });
  await page.goto(`${FRONTEND}/login`);
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/\/dashboard/, { timeout: 15000 });

  await page.goto(`${FRONTEND}/jobs`, { waitUntil: 'networkidle' });
  await page.click('button:has-text("Filtrele")');
  await page.waitForSelector('text=Sonuçları Göster');
  const drawerText = await page.locator('.fixed.inset-0.z-50').textContent();
  results.filter_labels_tr =
    drawerText?.includes('En düşük güven skoru') &&
    drawerText?.includes('En düşük uyum skoru') &&
    !drawerText?.includes('Minimum Trust Score')
      ? 'PASS'
      : 'FAIL';

  await browser.close();
}

await run();
console.log(JSON.stringify({ viewport: '390x844', results }, null, 2));
