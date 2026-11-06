# A.I-Check

A.I-Check adalah aplikasi web untuk mengunggah gambar dan melihat prediksi dari
layanan deteksi AI. Pengguna dapat membuat akun, menjalankan pemeriksaan, lalu
melihat dan menghapus riwayat analisis milik akunnya.

> **Batas interpretasi:** skor yang ditampilkan adalah estimasi probabilitas AI
> dari layanan deteksi, bukan kepastian atau ukuran akurasi kategori. Prediksi
> dapat keliru dan bukan bukti asal-usul gambar.

## Teknologi

- Laravel 12 dan PHP 8.2 atau lebih baru untuk aplikasi web dan API internal.
- SQLite sebagai koneksi database bawaan; konfigurasi Laravel dapat diarahkan
  ke database lain dengan mengatur variabel `DB_*`.
- Layanan deteksi lokal yang dikonfigurasi melalui `AI_DETECTOR_URL` untuk
  memproses gambar melalui endpoint HTTP.
- JavaScript, Vite 7, Tailwind CSS 4, dan Laravel Vite Plugin untuk aset
  frontend.

## Prasyarat

Pada lingkungan pengembangan Windows yang digunakan proyek ini:

- PHP 8.2+ dengan ekstensi yang dibutuhkan Laravel, Composer, dan Node.js/npm
  yang kompatibel dengan Vite 7.
- Layanan deteksi yang menyediakan API `POST /detect` sesuai kontrak di bawah.
- PowerShell untuk contoh perintah di bawah.

## Instalasi

Buka PowerShell di root repositori. Periksa versi PHP, Composer, dan Node/npm
sebelum memulai:

```powershell
php --version
composer --version
node --version
npm --version
```

### Dependency PHP dan environment Laravel

```powershell
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
```

Jalankan `key:generate` hanya saat menyiapkan environment baru. Jangan
mengganti `APP_KEY` pada instalasi yang sudah digunakan karena perubahan itu
dapat membuat data terenkripsi yang ada tidak dapat dibaca.

### Database dan migrasi aman

`.env.example` menggunakan SQLite dan `database/database.sqlite`. Jika file
SQLite belum ada, buat hanya file baru:

```powershell
if (-not (Test-Path database\database.sqlite)) {
    New-Item -ItemType File -Path database\database.sqlite | Out-Null
}
```

Periksa koneksi dan migrasi yang belum diterapkan sebelum menjalankan migrasi:

```powershell
php artisan migrate:status
php artisan migrate
```

`php artisan migrate` menerapkan migrasi yang belum berjalan pada database
terpilih dan tidak menghapus seluruh database. Tetap pastikan
`DB_CONNECTION`/`DB_DATABASE` menunjuk ke target yang benar dan buat backup
sebelum perubahan database penting. **Jangan gunakan `migrate:fresh` atau
`db:wipe` pada database yang berisi data.** Jangan menjalankan seeder pada
database berisi data tanpa meninjau dampaknya terlebih dahulu.

Untuk MySQL atau koneksi lain, ubah variabel `DB_*` di `.env` sesuai
konfigurasi database tersebut, lalu pastikan database sudah dibuat dan dapat
diakses sebelum menjalankan `migrate`.

### Dependency frontend

```powershell
npm install
npm run build
```

Untuk pengembangan dengan hot reload, gunakan `npm run dev` pada terminal
terpisah.

## Konfigurasi layanan deteksi

Laravel mengirim gambar tersimpan ke `AI_DETECTOR_URL` dengan default
`http://127.0.0.1:8000`; endpoint yang dipanggil adalah `/detect`. Atur
`AI_DETECTOR_URL`, `AI_DETECTOR_CONNECT_TIMEOUT`, dan `AI_DETECTOR_TIMEOUT`
melalui environment server-side sesuai layanan yang digunakan. Jangan
mengekspos endpoint ke browser atau menaruh kredensial layanan di frontend.

Layanan harus menerima unggahan multipart pada field `files` dan
mengembalikan JSON dengan `results[0].verdict` dan `results[0].p_ai`.
`verdict` harus salah satu dari `AI-GENERATED`, `AI-EDITED`, `REAL`, atau
`UNCERTAIN`; `p_ai` harus berupa angka antara 0 dan 1. Nilai `p_ai` disimpan
Laravel di field `confidence_score` untuk kompatibilitas dan ditampilkan
sebagai **Probabilitas AI**. Endpoint worker di `ai-service/worker.py`
menggunakan kontrak berbeda dan tidak langsung kompatibel dengan endpoint ini.

## Menjalankan aplikasi

Setelah dependency terpasang, `.env` dikonfigurasi, dan migrasi selesai:

1. Pastikan layanan deteksi yang dikonfigurasi melalui `AI_DETECTOR_URL`
   berjalan dan dapat dijangkau dari PHP.
2. Dari root repositori, jalankan server Laravel:

   ```powershell
   php artisan serve
   ```

3. Untuk pengembangan frontend, jalankan `npm run dev` di terminal lain.
   Jika memakai hasil build, jalankan `npm run build` dan biarkan server
   Laravel menyajikan aset tersebut.
4. Buka URL lokal yang ditampilkan `artisan serve`, lalu daftar atau masuk
   melalui halaman autentikasi.

Perubahan environment untuk proses yang sudah berjalan mungkin memerlukan
restart PHP/Laragon. Jika konfigurasi Laravel dicache, bersihkan/perbarui
cache melalui prosedur environment Anda sebelum mencoba ulang.

## Menggunakan deteksi dan History

1. Masuk ke aplikasi dan buka **Image Checker**.
2. Pilih gambar JPEG, PNG, atau WebP berukuran maksimal 10 MB, lalu unggah
   untuk diproses.
3. Hasil menampilkan kelas yang diprediksi dan skor kelas tersebut. Gunakan
   hasil sebagai indikasi model saja, bukan bukti atau keputusan tunggal.
4. Buka **History/Riwayat** untuk mencari, memfilter, mengurutkan, melihat
   detail, atau menghapus analisis. Penghapusan satu item atau seluruh riwayat
   tersedia dari halaman tersebut.

Route unggah analisis dan halaman History memerlukan autentikasi. Data riwayat
ditampilkan berdasarkan akun yang sedang masuk. Laravel mengirim gambar ke
endpoint `/detect` pada layanan terkonfigurasi dan memvalidasi kategori serta
rentang `p_ai` dari respons.

## Privasi dan batasan operasional

- Aplikasi memvalidasi gambar di server dan menyimpan file pada disk Laravel
  privat `image-analyses`; metadata dan hasil analisis disimpan di database.
- Endpoint gambar dan riwayat memeriksa kepemilikan analisis terhadap akun
  yang sedang masuk. File yang tidak lagi direferensikan dihapus saat item
  riwayat dihapus, jika pembersihan berhasil.
- Laravel mengirim file gambar privat ke layanan pada `AI_DETECTOR_URL`.
  Default-nya loopback; jika URL diubah, pastikan layanan tujuan diizinkan
  menerima data tersebut dan gunakan transport yang sesuai dengan lingkungan.
- File gambar, database, log, dan backup dapat berisi data sensitif. Kebijakan
  retensi, backup, kontrol akses mesin, TLS/proxy, dan pengamanan deployment
  tetap menjadi tanggung jawab operator. Dokumentasi ini tidak menjamin
  keamanan deployment atau penghapusan backup/log di luar perilaku aplikasi.
- Ketersediaan dan kapasitas layanan deteksi berada di luar proses Laravel;
  layanan yang sibuk atau timeout dapat menyebabkan analisis gagal.

## Tes dan pemeriksaan

Jalankan dari root repositori:

```powershell
php artisan test --compact
npm run build
git diff --check
```

## Troubleshooting

### Layanan deteksi tidak dapat dihubungi atau gagal memproses

- Pastikan layanan aktif dan `AI_DETECTOR_URL` menunjuk ke host serta port
  yang benar. Default-nya `http://127.0.0.1:8000`.
- Pastikan layanan menyediakan `POST /detect`, menerima multipart field
  `files`, dan merespons sesuai format `results[0]` yang dijelaskan di atas.
- Periksa log Laravel dan log layanan untuk diagnosis; jangan mencatat atau
  membagikan gambar sensitif maupun kredensial.
- Sesuaikan timeout koneksi dan request melalui `AI_DETECTOR_CONNECT_TIMEOUT`
  dan `AI_DETECTOR_TIMEOUT`, lalu mulai ulang proses PHP bila perlu.

### Konfigurasi salah

- Bandingkan nama variabel yang diperlukan dengan `.env.example` dan
  `config/services.php`; jangan menyalin secret nyata ke contoh atau repositori.
- Pastikan nilai database dan file SQLite benar sebelum migrasi. Untuk
  detektor, cocokkan URL dan timeout dengan konfigurasi layanan.
- Setelah mengubah environment, restart proses terkait dan periksa cache
  konfigurasi Laravel bila digunakan.

### Build frontend gagal

- Periksa versi Node.js yang kompatibel dengan Vite 7 serta output error.
- Jika dependency belum terpasang, jalankan `npm install`, lalu ulangi
  `npm run build`. Jangan menghapus lockfile sebagai langkah troubleshooting.
- Pastikan tidak ada error sintaks atau dependency yang gagal dipasang;
  perbaiki penyebab yang ditampilkan oleh build sebelum demo.

## Handover

### File dan nilai konfigurasi yang perlu diperiksa

- `.env.example`: nama dan nilai default variabel; jangan menambahkan secret
  nyata.
- `.env` lokal: `APP_KEY`, `APP_URL`, `DB_*`, dan `AI_DETECTOR_*`. File ini
  lokal/rahasia, jangan disalin ke laporan atau source control.
- `config/database.php`, `config/services.php`, dan
  `config/filesystems.php`: database, pengaturan detektor, dan lokasi storage
  privat.
- `composer.json`/`composer.lock` dan `package.json`/`package-lock.json`:
  dependency serta perintah yang tersedia.

### Startup dari kondisi berhenti

1. Pastikan database yang benar tersedia, dependency terpasang, dan layanan
   deteksi pada `AI_DETECTOR_URL` sudah dapat dijangkau.
2. Terapkan hanya migrasi yang belum berjalan dengan `php artisan migrate`.
3. Mulai `php artisan serve`; untuk mode pengembangan, mulai juga
   `npm run dev`. Untuk aset siap pakai, jalankan `npm run build`.
4. Masuk ke aplikasi dan lakukan satu unggahan uji yang tidak sensitif, lalu
   periksa hasil dan History.

### Pemeriksaan sebelum demo atau deployment

- Pastikan environment target, database, migrasi, permission storage, dan
  backup yang diperlukan sudah diperiksa; jangan memakai perintah migrasi
  destruktif.
- Pastikan layanan deteksi dan timeout sesuai, build frontend berhasil, serta
  tes Laravel lulus pada environment target.
- Pastikan aplikasi dapat login, mengunggah format yang didukung, menampilkan
  prediksi dan riwayat akun, serta menghapus item uji.
- Jangan gunakan gambar pribadi tanpa izin untuk demo. Jangan menyajikan skor
  sebagai jaminan probabilitas, akurasi, atau bukti asal gambar.
- Deployment memerlukan peninjauan tersendiri atas TLS, pengelolaan secret,
  retensi data, backup, logging, kapasitas layanan deteksi, dan kontrol akses host;
  checklist ini bukan sertifikasi keamanan.
