# FitCareer

Job platform for candidates and employers — Laravel API backend with a React + TypeScript frontend.

## Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 11, PHP 8.2+, MySQL |
| Frontend | React 18, TypeScript, Vite, Tailwind CSS |
| Queue | Database driver (`queue_jobs` table) |
| Auth | Laravel Sanctum |

## Requirements

- PHP 8.2+ with extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
- Composer 2.x
- Node.js 20+ and npm
- MySQL 8+
- XAMPP (or equivalent) for local Apache + MySQL

## Quick Start

### 1. Backend

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed   # if seeders exist
```

Configure `.env` with your database credentials and optional API keys (`GEMINI_API_KEY`, `JOOBLE_API_KEY`, etc.).

Serve via XAMPP: point document root to `public/` or use `php artisan serve`.

### 2. Frontend

```bash
cd frontend
npm install
npm run dev
```

Vite proxies `/api` to the Laravel backend (see `frontend/vite.config.ts`).

### 3. Job ingestion (optional)

```bash
php scripts/seed-remotive-source.php
php artisan jobs:import-source remotive --sync
php artisan jobs:source-health
```

## Project Structure

```
app/Services/Scraper/   # Job ingestion pipeline
frontend/src/         # React SPA
database/migrations/  # Schema
tests/                # PHPUnit tests
scripts/              # One-off seed & diagnostic scripts
```

## Tests

```bash
# Backend
php artisan test

# Frontend
cd frontend && npm test
```

## Documentation

- [`FITCAREER_CURRENT_STATE.md`](FITCAREER_CURRENT_STATE.md) — living project status & handoff context
- [`KARIYER_NET_ACCESS_REPORT.md`](KARIYER_NET_ACCESS_REPORT.md) — Kariyer.net WAF diagnostic

## Security

- Never commit `.env` or API keys.
- Use `.env.example` as the template for required variables.

## License

Proprietary — all rights reserved unless otherwise specified.
