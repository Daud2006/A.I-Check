# A.I-Check

Untuk pemakaian harian di Windows, ikuti `baca.txt`. Perintah
`composer run start:local` menjalankan website, worker, dan scheduler
dengan aset dari `public/build`. Setelah mengubah CSS atau JavaScript,
jalankan `npm.cmd run build` untuk memperbarui aset.

## Menyalakan detector lokal (Windows / Laragon)

Detector ada di `aidetect-engine` dan memproses gambar pada mesin lokal. Pada
penyiapan pertama, unduh hanya checkpoint ensemble `models_permissive` yang
diperlukan; unduhan bobot berasal dari Hugging Face Hub, bukan dari layanan inference.
Setelah unduhan, semua inference berjalan offline:

```powershell
Set-Location .\aidetect-engine
if (-not (Test-Path .\.venv-aidetect\Scripts\python.exe)) {
    py -3.12 -m venv .venv-aidetect
}
$python = Resolve-Path .\.venv-aidetect\Scripts\python.exe
$env:HF_HOME = Join-Path (Get-Location) '.hf_cache'
$env:HF_HUB_DOWNLOAD_TIMEOUT = '600'
& $python -m pip install -e '.[api]'
& $python scripts\fetch_models.py --ensemble-only --model-dir models_permissive
```

Setelah semua checkpoint berhasil diunduh, jalankan API pada terminal terpisah
dari root proyek:

```powershell
$python = Resolve-Path .\aidetect-engine\.venv-aidetect\Scripts\python.exe
$env:HF_HOME = (Resolve-Path .\aidetect-engine\.hf_cache).Path
$env:HF_HUB_OFFLINE = '1'
$env:AIDETECT_MODEL_DIR = (Resolve-Path .\aidetect-engine\models_permissive).Path
$env:AIDETECT_OPERATING_POINT = 'fpr5'
$env:AIDETECT_THREADS = '4'
$env:AIDETECT_MAX_BYTES = '10485760'
$env:AIDETECT_MAX_FILES = '1'
& $python -m uvicorn aidetect.api:app --app-dir .\aidetect-engine --host 127.0.0.1 --port 8766 --workers 1
```

Biarkan proses API berjalan saat aplikasi digunakan. Untuk antrean, `composer
run dev` menyalakan Laravel, Vite, dan worker yang memproses antrean `premium`
serta `default` (Free). Sebagai alternatif, siapkan konfigurasi Laravel sekali
(jika belum dilakukan) dan jalankan worker secara terpisah:

```powershell
php artisan analyses:work
```

Pastikan `.env` memakai `QUEUE_CONNECTION=database`, database sudah dimigrasikan
dengan tabel `jobs`, dan worker hanya dijalankan satu kali untuk development
(gunakan `composer run dev` atau perintah terpisah di atas, bukan keduanya
sekaligus). Jangan gunakan `queue:listen` dengan antrean `default` saja karena
pekerjaan Premium masuk ke antrean `premium`. Hentikan proses dengan `Ctrl+C`.

A.I-Check adalah aplikasi web untuk mengunggah gambar dan melihat prediksi dari
layanan deteksi AI. Pengguna dapat membuat akun, menjalankan pemeriksaan, lalu
melihat dan menghapus riwayat analisis milik akunnya.

> **Batas interpretasi:** skor yang ditampilkan adalah estimasi probabilitas AI
> dari layanan deteksi, bukan kepastian atau ukuran akurasi kategori. Prediksi
> dapat keliru dan bukan bukti asal-usul gambar.

## Teknologi

- Laravel 12 dan PHP 8.2 atau lebih baru untuk aplikasi web dan API internal.
- Python 3.12 untuk engine deteksi lokal.
- SQLite sebagai koneksi database bawaan; konfigurasi Laravel dapat diarahkan
  ke database lain dengan mengatur variabel `DB_*`.
- Model permissive terunduh satu kali dan tersedia di `aidetect-engine/.hf_cache`.
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

Laravel mengirim gambar tersimpan hanya ke `http://127.0.0.1:8766` secara
default. Klien membatasi URL ke loopback `127.0.0.1`, menolak redirect, lalu
memeriksa `/health` dan `/info` sebelum memanggil `/detect`. API harus
menunjukkan bahwa model sudah dimuat dan ensemble berlisensi permissive yang
diharapkan aktif. Atur `AI_DETECTOR_URL`, `AI_DETECTOR_CONNECT_TIMEOUT`,
`AI_DETECTOR_HEALTH_TIMEOUT`, dan `AI_DETECTOR_TIMEOUT` melalui environment
server-side; jangan mengekspos endpoint ke browser.

Endpoint `/detect` menerima multipart field `files`. Laravel memvalidasi
`results[0].verdict`, `results[0].p_ai`, threshold, dan skor seluruh anggota
ensemble. Verdict (`AI-GENERATED`, `AI-EDITED`, `REAL`, atau `UNCERTAIN`) serta
`p_ai` tersimpan langsung; probabilitas tidak dibalik. Rincian basis, operating
point, ambang, skor per model, metadata, dan versi model disimpan terpisah pada
`prediction_details`. Kolom baru nullable sehingga analisis lama tetap dapat
dibaca.

## Menjalankan aplikasi

Setelah dependency terpasang, `.env` dikonfigurasi, dan migrasi selesai:

1. Pastikan API lokal berjalan pada `127.0.0.1:8766` dengan ensemble
   `models_permissive` dan mode offline.
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
- Laravel hanya mengirim file gambar privat ke `127.0.0.1`; konfigurasi lain
  selain loopback ditolak dan redirect HTTP dinonaktifkan. Bobot model diunduh
  satu kali dari Hugging Face Hub saat pemasangan; inference berjalan dengan
  `HF_HUB_OFFLINE=1` dan tidak mengirim gambar ke layanan inference pihak ketiga.
- File gambar, database, log, dan backup dapat berisi data sensitif. Kebijakan
  retensi, backup, kontrol akses mesin, TLS/proxy, dan pengamanan deployment
  tetap menjadi tanggung jawab operator. Dokumentasi ini tidak menjamin
  keamanan deployment atau penghapusan backup/log di luar perilaku aplikasi.
- Ketersediaan dan kapasitas layanan deteksi berada di luar proses Laravel;
  layanan yang sibuk atau timeout dapat menyebabkan analisis gagal.

## Uji langganan dengan Midtrans Sandbox

1. Masuk sebagai admin dan buka **Admin → Paket**. Buat dan aktifkan paket
   Premium berbayar dalam IDR dengan periode bulanan atau tahunan. Tentukan
   harga dan kuota analisis sesuai kebijakan proyek.
2. Atur `MIDTRANS_SERVER_KEY` dengan Server Key Sandbox pada konfigurasi lokal
   dan gunakan `MIDTRANS_IS_PRODUCTION=false`. Checkout ini memakai halaman
   pembayaran Midtrans Snap melalui redirect dengan pilihan DANA dan QRIS.
   Pastikan kanal DANA aktif pada akun merchant agar dapat ditampilkan. QRIS
   generik memerlukan GoPay QRIS atau ShopeePay QRIS aktif pada akun Midtrans.
3. Di dashboard Sandbox Midtrans, isi **Payment Notification URL** dengan URL
   HTTPS lingkungan uji yang diakhiri `/webhooks/midtrans`. Midtrans harus
   dapat menjangkau endpoint ini dari internet. Alamat localhost Laragon
   dapat membuka katalog dan memulai checkout, tetapi tidak menerima webhook
   langsung dari Midtrans.
4. Masuk dengan akun pengguna uji, buka **Langganan**, dan pilih paket. Setelah
   pembayaran uji, periksa **Riwayat transaksi** dan **Profil**. Transaksi
   menjadi berhasil dan paket mulai berlaku sesuai masa langganan setelah
   aplikasi menerima notifikasi yang valid.

[Panduan Snap](https://docs.midtrans.com/docs/snap-snap-integration-guide) ·
[Panduan DANA](https://docs.midtrans.com/reference/dana) ·
[Panduan QRIS](https://docs.midtrans.com/reference/other-qris) ·
[Panduan webhook](https://docs.midtrans.com/docs/https-notification-webhooks)

## Uji OTP lokal dengan Mailpit

Jalankan Mailpit di PowerShell terpisah jika layanan belum aktif.

```powershell
& 'C:\laragon\bin\mailpit\1.22.3\mailpit.exe' --smtp '127.0.0.1:1025' --listen '127.0.0.1:8025'
```

Gunakan pengaturan berikut di `.env` lokal, lalu mulai ulang website.

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_URL=null
MAIL_FROM_ADDRESS=aicheck@example.test
```

Minta kode untuk akun uji yang terdaftar, lalu buka kotak masuk Mailpit
di `http://127.0.0.1:8025`.

## Analisis tertahan

Halaman Deteksi Gambar berhenti menunggu setelah sekitar dua menit. Status
analisis masih tersedia di Riwayat. Scheduler menandai analisis yang tetap
menunggu atau memproses selama 10 menit sebagai gagal dan mengembalikan kuota.
`composer run dev` menyalakan scheduler. Jika menjalankan layanan terpisah,
gunakan `php artisan schedule:work` atau jalankan `php artisan analyses:recover`
untuk memeriksa antrean secara manual.

## Tes dan pemeriksaan

Jalankan dari root repositori:

```powershell
php artisan test --compact
npm run build
git diff --check
```

## Troubleshooting

### Layanan deteksi tidak dapat dihubungi atau gagal memproses

- Pastikan API aktif pada `127.0.0.1:8766`, seluruh model sudah dimuat, dan
  `/info` menunjukkan ensemble `models_permissive` dengan lisensi yang sesuai.
- Pastikan proses dijalankan dari konfigurasi engine yang sama dengan
  `AIDETECT_MODEL_DIR=aidetect-engine/models_permissive` dan
  `HF_HUB_OFFLINE=1`.
- Periksa log Laravel dan log layanan untuk diagnosis; jangan mencatat atau
  membagikan gambar sensitif maupun kredensial.
- Sesuaikan timeout koneksi dan request melalui `AI_DETECTOR_CONNECT_TIMEOUT`
  dan `AI_DETECTOR_TIMEOUT`, lalu mulai ulang proses PHP bila perlu.

### Konfigurasi salah

- Bandingkan nama variabel yang diperlukan dengan `.env.example` dan
  `config/services.php`; jangan menyalin secret nyata ke contoh atau repositori.
- Pastikan nilai database dan file SQLite benar sebelum migrasi. Untuk
  detektor, gunakan loopback `127.0.0.1` dan cocokkan timeout pada
  `AI_DETECTOR_*`.
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
