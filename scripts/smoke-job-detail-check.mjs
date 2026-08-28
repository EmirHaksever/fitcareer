import { chromium } from 'playwright';

const email = `smoke-ui-check-${Date.now()}@example.test`;
const password = 'SmokeTest123!';

await fetch('http://127.0.0.1:8000/api/v1/auth/register', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  body: JSON.stringify({
    name: 'Smoke',
    email,
    password,
    password_confirmation: password,
    role: 'candidate',
  }),
});

const browser = await chromium.launch({ headless: true });
const page = await (await browser.newContext({ viewport: { width: 1280, height: 800 } })).newPage();

await page.goto('http://localhost:5173/login');
await page.fill('input[name="email"]', email);
await page.fill('input[name="password"]', password);
await page.click('button[type="submit"]');
await page.waitForURL('**/dashboard', { timeout: 15000 });

await page.goto('http://localhost:5173/jobs/software-engineer-3DC7AB606B', { waitUntil: 'networkidle' });
await page.waitForTimeout(1500);
const english = {
  isTanim: await page.locator('text=İş Tanımı').count(),
  engWarn: await page.locator('text=İngilizce').count(),
  trustTab: await page.locator('button', { hasText: 'Güvenilirlik Analizi' }).count(),
};

await page.goto('http://localhost:5173/jobs/saglik-sigortasi-satis-uzmani-43E9278F7F', {
  waitUntil: 'networkidle',
});
await page.waitForTimeout(1500);
const turkish = {
  isTanim: await page.locator('text=İş Tanımı').count(),
  engWarn: await page.locator('text=İngilizce').count(),
};

// Settings password wrong current
await page.goto('http://localhost:5173/settings', { waitUntil: 'networkidle' });
await page.fill('input[type="password"]', 'WrongCurrent123!');
const pwInputs = page.locator('input[type="password"]');
await pwInputs.nth(1).fill('NewSmoke789!');
await pwInputs.nth(2).fill('NewSmoke789!');
await page.click('button[type="submit"]');
await page.waitForTimeout(1000);
const settingsError = await page.locator('text=incorrect').or(page.locator('.text-danger')).count();

console.log(JSON.stringify({ english, turkish, settingsError }, null, 2));
await browser.close();
