# FitCareer

Aday ve işveren için iş platformu. Laravel API + React / TypeScript arayüz: kayıt/giriş, CV profili, iş arama, ilan taslak/yayın, başvuru, doğrulama, Trust Score ve Fit Score.

Personal portfolio project — not a company codebase.

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

Job listings are ingested from ~30 active sources across the following ATS
integrations, plus a dedicated Kariyer.net parser:

- Lever
- Greenhouse
- Workable
- Ashby
- Recruitee
- Kariyer.net (scraper)

Seed the source records for each provider, then trigger an import:

```bash
php scripts/seed-lever-sources.php
php scripts/seed-greenhouse-sources.php
php scripts/seed-workable-sources.php
php scripts/seed-ashby-sources.php
php scripts/seed-recruitee-sources.php
php scripts/seed-kariyer-net-source.php

php artisan jobs:import-source <source-name> --sync
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

## Security

- Never commit `.env` or API keys.
- Use `.env.example` as the template for required variables.

## License

Proprietary — all rights reserved unless otherwise specified.
