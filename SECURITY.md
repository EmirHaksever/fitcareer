# FitCareer secret rotasyonu

Gerçek API anahtarları yalnızca lokal `.env` veya deployment secret store içinde tutulmalıdır. `.env.example` bilerek boş bırakılmıştır ve canlıya kopyalanmamalıdır.

Rotasyon kontrol listesi:

1. Gemini, Adzuna ve Jooble sağlayıcı panellerinden yeni anahtarları üret.
2. Eski anahtarları sağlayıcı tarafında iptal et.
3. Lokal `.env` içinde yalnızca yeni değerleri tanımla; anahtarları Git'e veya frontend bundle'a koyma.
4. Deploy ortamında secret store/CI değişkenlerini güncelle ve uygulamayı yeniden başlat.
5. `php artisan config:clear` ve kaynak sağlık ekranı ile bağlantıyı doğrula.

Mevcut uygulama kodu frontend'e API anahtarı taşımaz; kaynak importları backend queue üzerinden çalışır. İlan kaynakları `external_id` + içerik hash ile tekrar kontrol edilir ve kaynak sağlığı admin ekranından izlenir.
