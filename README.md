# Fabrika QR Bakım Takip Sistemi

Sürüm: `1.0V`

PHP 8+, MariaDB, mail, Telegram ve WhatsApp bildirimlerine hazır, framework bağımsız bir cihaz kimliği ve bakım takip uygulaması.

## Özellikler

- Yayına ilk alındığında otomatik kurulum sihirbazı
- MariaDB tablo kurulumu veya SQL yedeğinden geri dönüş
- Site adı, taban URL, mail ve bildirim ayarları
- Cihaz kodu formatı: `ANA-TR-2026-55`
- Seri numarası, kurulum tarihi, bakım periyodu ve yetkili mail tanımlama
- QR kodu SVG olarak indirme ve yazdırılabilir etiket sayfası
- QR okutulunca tanımlı mail adreslerine 48 saat geçerli, her talepte yenilenen giriş linki gönderme
- Bakım yaklaşırken N gün önce bildirim gönderme
- Bakım günü 48 saat aktif `Yapıldı`, `Yapılmadı`, `Başka zamana planlandı` butonları
- `Yapılmadı` seçilirse risk/hata sonuçlarını içeren okunma onaylı mail
- Admin panelinden manuel yedek alma ve günlük yedeği mail atma
- Telegram Bot API ve genel WhatsApp webhook entegrasyonu için servis noktaları
- PWA manifest, service worker, çevrimdışı sayfa ve tarayıcıya kurulabilir uygulama desteği
- Web Push aboneliği, test bildirimi ve bakım hatırlatmalarını anlık tarayıcı bildirimi olarak gönderme
- GrapesJS ile mail şablonu ve rapor şablonu tasarlama

## Gereksinimler

- PHP 8.1 veya üzeri
- `pdo_mysql` eklentisi
- `curl`, `mbstring`, `openssl` eklentileri
- Composer
- MariaDB 10.5 veya üzeri
- Web sunucusu belge kökü: `public/`

## Yerel Çalıştırma

PHP kurulu olduğunda:

```bash
composer install --no-dev --prefer-dist
php -S 127.0.0.1:8000 -t public public/router.php
```

Tarayıcıda `http://127.0.0.1:8000` adresine gidin. `config/config.php` yoksa kurulum sihirbazı açılır.

## Hosting Kurulumu

Web sunucusunun belge kökü mutlaka `public/` klasörü olmalıdır. Uygulama dosyaları, `src/`, `config/`, `database/`, `storage/` ve `cron/` klasörleri web kökünün dışında kalır.

Paylaşımlı hostingte veya Herd gibi lokal ortamda tüm proje dosyalarını site ana klasörüne yükleyin, domain belge kökünü aynı klasörün içindeki `public/` dizinine yönlendirin.

Herd kullanıyorsanız proje şu klasörde durabilir:

```text
~/Herd/qr-bakim
```

Domain belge kökü şu klasör olmalıdır:

```text
~/Herd/qr-bakim/public
```

Sonra sihirbazı açın:

```text
http://qr-bakim.test/install
```

Varsayılan lokal MariaDB bilgileri:

```text
Host: 127.0.0.1
Port: 3306
Veritabanı: factory_qr
Kullanıcı: root
Şifre: boş bırakın
```

Kurulum sihirbazındaki `SQL Baglantisini Test Et` butonu ile bu bilgileri kuruluma başlamadan önce kontrol edebilirsiniz.

Kurulum ekranında yalnızca MariaDB bilgileri ve admin giriş bilgileri istenir. Site adı, site adresi, zaman dilimi, mail ve bildirim ayarları varsayılan değerlerle kurulur; daha sonra admin panelinden geliştirilebilir.

Sihirbaz görünmüyorsa `config/config.php` dosyasının oluşmadığından veya eski kurulumdan kalmadığından emin olun.

## Mail Ayarları

Varsayılan mail gönderimi Yandex SMTP üzerinden hazırlanmıştır:

```text
Sunucu: smtp.yandex.com.tr
Port: 465
Güvenlik: SSL
Kullanıcı/Gönderen: bigaofis@alarmbigabilisim.com
```

Admin panelinde `Ayarlar` ekranından SMTP bilgileri ve günlük yedek mail alıcıları güncellenebilir.

## Bakım Bildirim Günleri

Admin panelinde `Ayarlar > Bakım Bildirim Günleri` bölümünden varsayılan gün listesi yönetilir. Cihaz ekleme ve düzenleme ekranında bu günler hızlı seçenek olarak gelir; kullanıcı ayrıca `+ Gün Ekle` butonu ile cihaza özel bildirim günü ekleyebilir.

## PWA ve Web Push

Uygulama PWA uyumludur:

```text
Manifest: /manifest.webmanifest
Service Worker: /service-worker.js
Çevrimdışı sayfa: /offline
```

Admin panelindeki `Ayarlar` ekranından:

```text
Bildirimleri Aç
Test Bildirimi Gönder
```

butonlarıyla tarayıcı Web Push aboneliği başlatılır ve test edilir. Web Push için sunucuda HTTPS gerekir; `localhost` test ortamında tarayıcılar HTTPS olmadan da izin verebilir.

VAPID anahtarları kurulumda veya ilk Web Push kullanımında otomatik üretilir ve `config/config.php` içinde saklanır. Bu dosya GitHub'a gönderilmez.

## Şablon Editörü

Admin panelinde:

```text
Mail Şablonları
Raporlar
```

ekranlarından GrapesJS tabanlı görsel editör açılır. Sistem bakım mailleri için varsayılan şablonlar otomatik oluşturulur ve düzenlenebilir:

```text
maintenance_upcoming
maintenance_due
maintenance_no_response
maintenance_hazard
```

Şablonlarda `{{device_code}}`, `{{maintenance_date}}`, `{{days_left}}`, `{{done_url}}`, `{{ack_url}}` gibi değişkenler kullanılabilir.

Şablon düzenleme ve ön izleme ekranlarında `Test Mail Gönder` alanı bulunur. Ayrıca `Mail Test Platformu` sayfasında bir mail şablonu seçilip birden fazla test adresi girilerek örnek değişkenlerle toplu test gönderimi yapılabilir. Rapor şablonları da ön izleme ekranından test maili olarak kontrol edilebilir.

## Test

```bash
composer install
find . -path './.git' -prune -o -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/smoke.php
```

GitHub'a yüklendiğinde `.github/workflows/php.yml` otomatik olarak PHP 8.2 ve 8.3 üzerinde syntax, smoke ve MariaDB entegrasyon kontrollerini çalıştırır.

## Cron Görevleri

Bakım bildirimleri için:

```bash
* * * * * /usr/bin/php /path/to/project/cron/maintenance_notifications.php
```

Günlük yedek ve mail gönderimi için:

```bash
0 2 * * * /usr/bin/php /path/to/project/cron/daily_backup.php
```

## Not

Web Push için Composer ile `minishlink/web-push` paketi kullanılır. Mail ve rapor tasarımında GrapesJS yerel asset olarak sunulur. QR kod üretimi sunucu tarafında SVG çıktısı olarak yapılır.
