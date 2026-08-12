# Kariyer.net Access Diagnostic Report

**Generated:** 2026-08-12  
**Script:** `scripts/kariyer-net-access-diagnostic.php`  
**Raw evidence:** `KARIYER_NET_ACCESS_REPORT.json`

---

## Executive Summary

Kariyer.net production ingestion fails with **HTTP 403** for both listing and detail URLs. The block is **not caused by FitCareer's scraper User-Agent or Accept headers** — five different client profiles (scraper default, Chrome browser, curl, minimal, JSON Accept) all receive **identical 403 responses** with the same body length.

The response body indicates **PerimeterX bot protection** (`PXqzR3QUY9`), not Cloudflare. The server returns a JavaScript challenge/captcha page titled **"Access to this page has been denied"** instead of SSR job HTML.

**Conclusion:** This is an **automated access control block at the edge/WAF layer**, likely triggered by datacenter/residential IP reputation, non-browser TLS fingerprint, or missing browser JavaScript execution — **not a parser or normalization bug** in FitCareer.

**Recommended action:** Do **not** implement CAPTCHA solving, proxy rotation, or anti-bot bypass. Pursue **official partnership/API** with Kariyer.net or alternate Turkish sources with permitted API access (Jooble, etc.).

---

## Test Environment

| Field | Value |
|-------|-------|
| OS | Windows (PHP 8.2.12) |
| Scraper UA | `FitCareer/1.0 (job-ingestion; contact@fitcareer.local)` |
| Accept-Language | `tr-TR,tr;q=0.9,en-US;q=0.8,en;q=0.7` |
| Listing URL | `https://www.kariyer.net/is-ilanlari/yazilim` |
| Detail URL | `https://www.kariyer.net/is-ilani/fonet-lider-yazilim-gelistirme-uzmani-full-stack-4477112` |

---

## HTTP Status Results

### Listing URL (`/is-ilanlari/yazilim`)

| Profile | Status | Body Length | Redirect | Job Links | Title |
|---------|--------|-------------|----------|-----------|-------|
| scraper_default | **403** | 4961 | No | No | Access to this page has been denied |
| browser_chrome | **403** | 4961 | No | No | Access to this page has been denied |
| curl_default | **403** | 4961 | No | No | Access to this page has been denied |
| minimal | **403** | 4961 | No | No | Access to this page has been denied |
| json_accept | **403** | 4961 | No | No | Access to this page has been denied |

### Detail URL (`/is-ilani/...`)

| Profile | Status | Body Length | Title |
|---------|--------|-------------|-------|
| All 5 profiles | **403** | 5089 | Access to this page has been denied |

**Observation:** Identical status and body length across all profiles → **not User-Agent or Accept header specific**.

---

## Response Body Analysis

Snippet from 403 response (listing):

```
Access to this page has been denied
window._pxAppId='PXqzR3QUY9';
window._pxJsClientSrc='/qzR3QUY9/init.js';
var pxCaptchaSrc='/qzR3QUY9/captcha/PXqzR3QUY9/captcha.js?...'
```

| Signal | Detected | Meaning |
|--------|----------|---------|
| PerimeterX (`_pxAppId`, `PXqzR3QUY9`) | **Yes** | Enterprise bot management / WAF |
| Cloudflare | No | Not Cloudflare |
| Login wall | No | Not authentication-required |
| Nuxt SSR content | No | Real job page not served |

---

## robots.txt

- **Status:** 200 OK
- **`/is-ilanlari` disallowed:** No
- **`/is-ilani` disallowed:** No

The 403 is **not** robots-driven.

---

## Historical Context

| Period | Behavior |
|--------|----------|
| Earlier feasibility | **PASS** — SSR HTML, jobs parsed |
| V1.0 ingestion | **PASS** — 12 Kariyer jobs in DB |
| V1.1 production (2026-08-12) | **FAIL** — HTTP 403 |

Indicates **access policy or IP reputation change**, not code regression.

---

## Reproduction

```powershell
php scripts/kariyer-net-access-diagnostic.php
php artisan jobs:test-ingestion --source=kariyer-net
```
