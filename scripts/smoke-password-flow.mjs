import { chromium } from 'playwright';

const email = `smoke-pwflow-${Date.now()}@example.test`;
const password = 'SmokeTest123!';
const newPassword = 'NewSmoke456!';

await fetch('http://127.0.0.1:8000/api/v1/auth/register', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  body: JSON.stringify({
    name: 'Smoke PW',
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
await page.waitForURL('**/dashboard');

await page.goto('http://localhost:5173/settings');
const inputs = page.locator('input[type="password"]');
await inputs.nth(0).fill(password);
await inputs.nth(1).fill(newPassword);
await inputs.nth(2).fill(newPassword);
await page.click('button[type="submit"]');
await page.waitForURL('**/login', { timeout: 15000 });
const successMsg = (await page.locator('text=Şifren güncellendi').count()) > 0;
await page.fill('input[name="email"]', email);
await page.fill('input[name="password"]', newPassword);
await page.click('button[type="submit"]');
await page.waitForURL('**/dashboard', { timeout: 15000 });

console.log(
  JSON.stringify({
    redirectedToLogin: true,
    successMessage: successMsg,
    reloginWorks: page.url().includes('/dashboard'),
  }),
);

await browser.close();
