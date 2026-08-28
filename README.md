# UT Asset Management System

UT Asset Management System adalah aplikasi web untuk mencatat, memantau, dan melaporkan aset perusahaan, khususnya aset **Information Technology (IT)** dan **General Affairs (GA)**. Aplikasi ini juga mendukung transaksi barang, stocktaking, persetujuan pengguna, ekspor laporan, dan notifikasi melalui email.

## Fitur Utama

- Dashboard ringkasan aset dan aktivitas.
- Pengelolaan aset IT dan GA.
- Pencatatan barang masuk dan barang keluar.
- Stocktaking aset beserta foto, kondisi, dan alur persetujuan.
- Manajemen akun dengan peran `admin`, `it`, `ga`, dan `user`.
- Persetujuan pendaftaran akun dan permintaan reset password.
- Pencarian, filter, dan pemantauan data aset.
- Ekspor data dan laporan ke Excel atau PDF.
- Unggah foto serta lampiran aset dan transaksi.
- Notifikasi email melalui SMTP.
- Sinkronisasi opsional ke Google Spreadsheet melalui Google Apps Script.

## Teknologi

- PHP 8.1 atau lebih baru
- MySQL atau MariaDB
- HTML, CSS, dan JavaScript
- PHPMailer
- Apache atau LiteSpeed dengan dukungan `.htaccess`
- Composer

## Struktur Proyek

```text
api/                 Endpoint API berdasarkan modul
app/                 Bootstrap, helper, service, dan template email
config/              Konfigurasi database, email, upload, dan spreadsheet
css/                 Stylesheet aplikasi
docs/                Skema database dan panduan deployment
google_apps_script/  Script untuk integrasi Google Spreadsheet
img/                 Gambar dan logo aplikasi
js/                  Logika antarmuka pengguna
uploads/             Penyimpanan file unggahan
*.html               Halaman utama aplikasi
```

## Persyaratan

Pastikan lingkungan pengembangan memiliki:

- PHP 8.1+
- Ekstensi PHP `mysqli`
- MySQL/MariaDB
- Composer
- Apache dengan `mod_rewrite` dan dukungan `.htaccess`
- Folder `uploads/` yang dapat ditulis oleh web server

## Instalasi Lokal dengan XAMPP

1. Clone repository ke dalam folder `htdocs`:

   ```bash
   git clone https://github.com/r23774801-arch/webdataasset.git
   cd webdataasset
   ```

2. Instal dependensi PHP:

   ```bash
   composer install
   ```

3. Buat database bernama `db_ut_assets`, lalu import [`docs/schema.sql`](docs/schema.sql) menggunakan phpMyAdmin atau MySQL CLI.

4. Salin `.env.example` menjadi `.env` dan sesuaikan konfigurasinya:

   ```env
   APP_URL=http://localhost/webdataasset

   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=db_ut_assets

   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USERNAME=
   SMTP_PASSWORD=
   SMTP_ENCRYPTION=tls
   MAIL_FROM_NAME=UT Asset Management System
   MAIL_FROM_ADDRESS=

   SPREADSHEET_WEB_APP_URL=
   SPREADSHEET_TOKEN=
   SPREADSHEET_TIMEOUT=3

   UPLOAD_MAX_SIZE=5242880
   ```

5. Jalankan Apache dan MySQL dari XAMPP.

6. Buka aplikasi melalui:

   ```text
   http://localhost/webdataasset/
   ```

> Jangan commit file `.env`. File tersebut berisi konfigurasi dan kredensial yang bersifat rahasia.

## Konfigurasi Email

Isi variabel SMTP pada `.env` untuk mengaktifkan email notifikasi. Jika menggunakan Gmail, gunakan **App Password**, bukan password utama akun. Alamat penerima notifikasi admin diambil dari akun aktif yang memiliki peran `admin`.

## Sinkronisasi Google Spreadsheet

MySQL/MariaDB tetap menjadi sumber data utama. Integrasi spreadsheet hanya berfungsi sebagai salinan untuk pelaporan atau analitik dan dapat dinonaktifkan dengan membiarkan `SPREADSHEET_WEB_APP_URL` kosong.

Script integrasi tersedia di [`google_apps_script/Code.gs`](google_apps_script/Code.gs). Setelah script di-deploy sebagai Web App, masukkan URL deployment dan token yang sama ke `.env`.

## Deployment

Aplikasi dapat dijalankan pada shared hosting yang mendukung PHP 8.1+, MySQL/MariaDB, Apache atau LiteSpeed, dan `.htaccess`. Panduan deployment cPanel tersedia di [`docs/DEPLOY.md`](docs/DEPLOY.md).

Ringkasan proses deployment:

1. Upload seluruh file aplikasi ke document root hosting.
2. Jalankan `composer install` atau upload folder `vendor/` dari lingkungan lokal.
3. Buat database dan import `docs/schema.sql`.
4. Buat `.env` khusus production.
5. Pastikan folder `uploads/` writable.
6. Aktifkan HTTPS dan uji login, upload, email, serta ekspor laporan.

## Keamanan

- Kredensial disimpan melalui environment variable atau file `.env` yang diabaikan Git.
- Password pengguna disimpan dalam bentuk hash.
- File sensitif dan folder upload dilindungi menggunakan `.htaccess`.
- Detail error database dicatat pada log server dan tidak dikirimkan kepada pengguna.
- Gunakan HTTPS pada lingkungan production dan batasi hak akses user database.

## Dokumentasi Tambahan

- [Panduan deployment](docs/DEPLOY.md)
- [Skema database](docs/schema.sql)
- [Daftar pekerjaan lanjutan](docs/TODO.md)

## Lisensi

Belum ada lisensi open-source yang ditetapkan untuk repository ini. Hubungi pemilik repository sebelum menggunakan atau mendistribusikan aplikasi di luar kebutuhan proyek.
