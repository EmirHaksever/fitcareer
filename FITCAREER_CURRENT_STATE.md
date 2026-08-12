# FitCareer — Current State

**Last updated:** 2026-08-12  
**Workspace:** `c:\xampp\htdocs\fitcareer`

---

## Working

| Area | Status | Notes |
|------|--------|-------|
| Remotive ingestion | ✅ | Production import, 17 jobs, duplicate-safe |
| Ingestion queue | ✅ | `RunJobSourceImportJob`, retry/backoff |
| Scheduler | ✅ | `jobs:dispatch-scheduled-imports` every 5 min |
| Freshness | ✅ | `last_seen_at`, stale → expired |
| Source health | ✅ | `php artisan jobs:source-health` |
| Search API | ✅ | Expired published jobs filtered |
| Frontend source badges | ✅ | Remotive, Kariyer.net, FitCareer, Dış Kaynak |
| Saved jobs API | ✅ | `/candidate/saved-jobs` |
| Tests | ✅ | 319 PHPUnit + frontend Vitest |

## Blocked

| Source | Reason |
|--------|--------|
| Kariyer.net (live) | HTTP 403 — PerimeterX WAF. Parser works on fixtures; 12 historical jobs in DB. See `KARIYER_NET_ACCESS_REPORT.md`. |

## Architecture (Ingestion)

```
JobSource → JobSourceImportService
  → ScraperClientService (match on type + config.provider)
  → JobIngestionService → JobNormalizerService
  → ScrapedJobFreshnessService
  → JobImportRun + JobSourceHealthService
```

Providers today: `remotive`, `kariyer-net`. No adapter interface yet — add match arms for new sources.

## Key Commands

```bash
php artisan jobs:import-source remotive --sync
php artisan jobs:test-ingestion --source=remotive
php artisan jobs:source-health
php artisan queue:work
php scripts/kariyer-net-access-diagnostic.php
```

## Next Priorities

1. **Jooble** — obtain API key, add `provider=jooble` ApiIntegration source
2. **Kariyer.net** — pursue official partnership/feed (not WAF bypass)
3. **RemoteOK** — public JSON feed with attribution

## Do Not Break

- Remotive ingestion pipeline
- FitScoreCalculator / AI extraction (unless explicitly tasked)
- Duplicate key: `(job_source_id, external_id)`

## Environment Variables

See `.env.example`. Secrets: `GEMINI_API_KEY`, `ADZUNA_APP_ID`, `ADZUNA_APP_KEY`, `JOOBLE_API_KEY`.
