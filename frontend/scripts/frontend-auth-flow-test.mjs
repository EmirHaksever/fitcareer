/**
 * Simulates frontend AuthContext flow against a running Laravel API.
 * Usage: node scripts/frontend-auth-flow-test.mjs [apiBase]
 */
const apiBase = process.argv[2] ?? 'http://127.0.0.1:8000/api/v1';
const timestamp = Date.now();
const email = `fe.candidate.${timestamp}@fitcareer.test`;
const password = 'Password123!';

async function request(path, { method = 'GET', body, token } = {}) {
  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(`${apiBase}${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });

  const json = await response.json().catch(() => null);
  return { status: response.status, json };
}

function pass(label, ok, detail = '') {
  console.log(`${ok ? '[PASS]' : '[FAIL]'} ${label}${detail ? ` — ${detail}` : ''}`);
}

async function main() {
  console.log(`API_BASE=${apiBase}`);

  const emptyLogin = await request('/auth/login', {
    method: 'POST',
    body: { email: '', password: '' },
  });
  pass('Empty login -> 422', emptyLogin.status === 422);
  pass('Empty email error mapped', Boolean(emptyLogin.json?.errors?.email?.length));
  pass('Empty password error mapped', Boolean(emptyLogin.json?.errors?.password?.length));

  const invalidEmail = await request('/auth/login', {
    method: 'POST',
    body: { email: 'bad-email', password: 'x' },
  });
  pass('Invalid email -> 422', invalidEmail.status === 422);

  const wrongPassword = await request('/auth/login', {
    method: 'POST',
    body: { email: 'missing@example.com', password: 'wrong' },
  });
  pass('Wrong password -> 401', wrongPassword.status === 401);
  pass('Wrong password message', wrongPassword.json?.message === 'Invalid credentials.');

  const register = await request('/auth/register', {
    method: 'POST',
    body: {
      name: 'FE Candidate',
      email,
      password,
      password_confirmation: password,
      role: 'candidate',
    },
  });
  pass('Register candidate -> 201', register.status === 201);
  const token = register.json?.data?.token;
  pass('Token stored candidate', typeof token === 'string' && token.length > 0);

  const login = await request('/auth/login', {
    method: 'POST',
    body: { email, password },
  });
  pass('Login success -> 200', login.status === 200);
  const loginToken = login.json?.data?.token;
  pass('Login token issued', typeof loginToken === 'string' && loginToken.length > 0);

  const me = await request('/auth/me', { token: loginToken });
  pass('Session persistence via /me -> 200', me.status === 200);

  const logout = await request('/auth/logout', { method: 'POST', token: loginToken });
  pass('Logout -> 200', logout.status === 200);

  const meAfterLogout = await request('/auth/me', { token: loginToken });
  pass('Token invalid after logout -> 401', meAfterLogout.status === 401);

  const companyEmail = `fe.company.${timestamp}@fitcareer.test`;
  const companyRegister = await request('/auth/register', {
    method: 'POST',
    body: {
      name: 'FE Company',
      email: companyEmail,
      password,
      password_confirmation: password,
      role: 'company',
      company_name: 'FE Company',
    },
  });
  pass('Register company -> 201', companyRegister.status === 201);
  pass('Company payload role', companyRegister.json?.data?.user?.role === 'company');

  const viteHealth = await fetch('http://localhost:5173/api/v1/health').then(async (r) => ({
    status: r.status,
    ok: r.ok,
    text: await r.text(),
  })).catch((error) => ({ status: 0, ok: false, text: String(error) }));
  pass('Vite proxy /api/v1/health reachable', viteHealth.ok, `status=${viteHealth.status}`);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
