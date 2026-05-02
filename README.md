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
- QR okutulunca tanımlı mail adreslerine tek kullanımlık doğrulama kodu gönderme
- Bakım yaklaşırken N gün önce bildirim gönderme
- Bakım günü 48 saat aktif `Yapıldı`, `Yapılmadı`, `Başka zamana planlandı` butonları
- `Yapılmadı` seçilirse risk/hata sonuçlarını içeren okunma onaylı mail
- Admin panelinden manuel yedek alma ve günlük yedeği mail atma
- Telegram Bot API ve genel WhatsApp webhook entegrasyonu için servis noktaları

## Gereksinimler

- PHP 8.1 veya üzeri
- `pdo_mysql` eklentisi
- MariaDB 10.5 veya üzeri
- Web sunucusu belge kökü: `public/`

## Yerel Çalıştırma

PHP kurulu olduğunda:

```bash
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

## Test

```bash
find . -path './.git' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
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

Bu depo harici PHP paketine ihtiyaç duymadan çalışacak şekilde tasarlandı. QR kod üretimi sunucu tarafında SVG çıktısı olarak yapılır.
