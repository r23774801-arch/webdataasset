# Panduan Deploy ke Shared Hosting (cPanel)

Sistem: UT Asset Management System (PHP + MySQL + Apache/LiteSpeed)

## 1. Persyaratan Hosting

| Kebutuhan | Minimum |
|-----------|---------|
| PHP | **8.1 atau 8.2** (PHPMailer 7.1.1 wajib 8.1+) |
| Database | MySQL / MariaDB |
| Web server | Apache atau LiteSpeed (dukung .htaccess) |
| Folder upload | `uploads/` harus writable (chmod 755) |
| Storage | ~100 MB cukup (vendor + uploads) |

## 2. File yang WAJIB di-upload ke public_html/

Upload SELURUH isi folder proyek (kecuali yang dikecualikan), struktur:

```
public_html/
├── .htaccess              ← WAJIB (proteksi keamanan)
├── .env                   ← GANTI sesuai hosting (lihat langkah 3)
├── .env.example           ← boleh ikut
├── *.html                 ← semua halaman
├── *.php                  ← semua endpoint
├── app/                   ← seluruh isi
├── config/                ← seluruh isi
├── css/                   ← seluruh isi
├── img/                   ← seluruh isi
├── js/                    ← seluruh isi
├── uploads/               ← wajib + chmod 755
└── vendor/                ← seluruh isi (jangan di-upload ulang dari git)
```

**JANGAN di-upload** (tidak boleh ada di hosting):
- Folder `.git/`
- Folder `.vscode/`
- File `.env` lama (buat yang baru)
- File `DEPLOY.md`
- File `schema.sql` setelah import database selesai
- File test/sampah apapun di root

> Tips: kalau upload via File Manager cPanel, zip isi folder (BUKAN folder induknya)
> lalu Extract di dalam public_html agar struktur langsung benar.

## 3. Isi .env versi hosting

Buat file `.env` baru di public_html (copy dari .env.example), isi:

```env
# --- SMTP Server (Gmail — pakai App Password, bukan password akun) ---
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=nama-akun-smtp@example.com
SMTP_PASSWORD=app-password-smtp
SMTP_ENCRYPTION=tls

# --- Sender identity ---
MAIL_FROM_NAME=UT Asset Management System
MAIL_FROM_ADDRESS=noreply@example.com

# --- Application base URL (WAJIB diisi URL hosting Anda) ---
APP_URL=https://nama-domain-anda.com/

# --- Database (isi sesuai cPanel → MySQL Databases) ---
DB_HOST=localhost
DB_USER=u1234567_nama_user
DB_PASS=password-database
DB_NAME=u1234567_nama_db

# --- Google Spreadsheet Sync ---
SPREADSHEET_WEB_APP_URL=https://script.google.com/macros/s/ID_DEPLOYMENT/exec
SPREADSHEET_TOKEN=random-token-panjang
SPREADSHEET_TIMEOUT=3
UPLOAD_MAX_SIZE=5242880
```

## 4. Langkah setelah upload

1. **Buat database** di cPanel → MySQL Databases, buat user + assign ke DB.
2. **Import `schema.sql`** lewat phpMyAdmin untuk database baru.
3. **Isi `.env`** sesuai langkah 3 dengan kredensial hosting.
4. **Chmod folder** `uploads/` = 755 (cPanel → File Manager → kanan → Change Permissions).
5. **Set PHP 8.1/8.2** di cPanel → Select PHP Version (MultiPHP Manager).
6. **Install SSL** (Let's Encrypt) dan pastikan akses lewat `https://`.
7. **Jalankan migrasi DB sekali**: login sebagai admin → buka `https://domain/migrate_db.php`.
8. **Hapus atau pindahkan `migrate_db.php` dan `schema.sql` dari public_html** setelah sukses.
9. **Tes**: login, buat data, cek email terkirim.

## 5. Yang perlu diperiksa kalau ada masalah

- Halaman 403 terus → cek `.htaccess` ter-upload, file `.env` di luar public_html?
- Error DB → cek DB_HOST/USER/PASS/NAME di `.env`
- Email tidak terkirim → cek App Password benar & SMTP_USERNAME
- Upload foto gagal → cek chmod `uploads/`
- Login langsung ke-blokir "terlalu banyak" → hapus folder `webdataaset_login`
  di direktori temp hosting (kadang `/tmp` di cPanel: `public_html/../tmp`)

## 6. Rekomendasi Hosting (2026)

Ketiganya mendukung PHP 8.x, MySQL, `.htaccess` (Apache/LiteSpeed), dan SSL gratis.
Pilih **LiteSpeed/Apache**, bukan NGINX-only, karena `.htaccess` root (CSP, blokir file
sensitif, rate-limit) hanya berjalan di keduanya.

| Provider | Paket | Harga/thn (perkiraan) | Kelebihan |
|----------|-------|----------------------|-----------|
| **DomaiNesia** (rekomendasi) | Starter | ±Rp198.000 (Rp16.500/bln) | NVMe + LiteSpeed, cPanel, Imunify360, harga renewal flat, support 24/7 |
| **Niagahoster** | Bayi | ±Rp238.000 (Rp19.800/bln) | LiteSpeed + cPanel, SSL gratis, uptime 99.98% (catatan: renewal naik 2–3×) |
| **IDCloudHost** | Cloud Starter | mulai ±Rp49.000/bln | Cloud NVMe, Plesk, PHP 7/8, ISO 27001, cocok developer/startup |

Checklist saat membeli: PHP 8.1/8.2 (via Select PHP Version), cPanel/Plesk,
`uploads/` writable, domain + SSL terpasang, lalu ikuti langkah 3–4 di atas.
