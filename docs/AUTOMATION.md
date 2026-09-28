# FitCareer ilan otomasyonu

İlan akışı üç parçadan oluşur:

1. Laravel scheduler her beş dakikada `jobs:dispatch-scheduled-imports` komutunu çalıştırır.
2. Komut, yalnızca `refresh_interval_minutes` süresi dolmuş aktif kaynakları queue'ya bırakır. Kaynakların varsayılan yenileme aralığı 360 dakikadır.
3. Database queue worker, kaynak importlarını tek tek işler. Import sırasında `external_id` + içerik hash duplicate kontrolü, kalite filtresi, freshness/expiry lifecycle ve kaynak sağlık kaydı uygulanır.

## Windows / XAMPP

Bir kez PowerShell ile çalıştır:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\install-fitcareer-automation.ps1
```

Bu işlem önce `FitCareer - Job Automation` adında bir Windows Task Scheduler görevi oluşturmayı dener. Yönetici yetkisi yoksa kullanıcının Startup klasörüne aynı işi yapan bir kısayol ekler. Kullanıcı Windows'a giriş yaptığında supervisor başlar; scheduler veya queue worker kapanırsa supervisor yeniden başlatır.

Mevcut oturumda elle başlatmak için:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run-fitcareer-workers.ps1
```

Durdurmak için:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\run-fitcareer-workers.ps1 -Stop
```

## Linux / production

Laravel scheduler için cron veya Supervisor kullanılmalı:

```cron
* * * * * cd /var/www/fitcareer && php artisan schedule:run >> /dev/null 2>&1
```

Queue worker ise Supervisor/systemd altında sürekli çalışmalıdır:

```bash
php artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=120
```

Import anahtarları backend environment secret store içinde tutulmalı; frontend bundle'a aktarılmamalıdır.
