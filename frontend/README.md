# FitCareer Frontend

React + TypeScript + Vite frontend for the FitCareer Laravel API.

## Development

```bash
cd frontend
npm install
npm run dev
```

The Vite dev server proxies `/api` to `http://localhost/fitcareer/public/api`.

Ensure the Laravel API is running via XAMPP before testing authenticated flows.

## Scripts

- `npm run dev` - start dev server
- `npm run build` - production build
- `npm run typecheck` - TypeScript check
- `npm run lint` - ESLint
- `npm run test` - Vitest unit tests
