# A.I-Check

A.I-Check adalah aplikasi web untuk mengunggah gambar dan melihat prediksi
klasifikasi lokal: `artificial` atau `human`. Pengguna dapat membuat akun,
menjalankan pemeriksaan, lalu melihat dan menghapus riwayat analisis milik
akunnya.

> **Batas interpretasi:** skor yang ditampilkan adalah probabilitas softmax
> model untuk kelas yang diprediksi. Skor tersebut bukan probabilitas pasti
> bahwa gambar dibuat oleh AI, bukan ukuran keyakinan dunia nyata yang
> terkalibrasi, dan bukan bukti asal-usul gambar. Prediksi dapat keliru.
> Repositori ini tidak menyatakan tingkat akurasi model yang tervalidasi
> secara independen.

## Teknologi

- Laravel 12 dan PHP 8.2 atau lebih baru untuk aplikasi web dan API internal.
- SQLite sebagai koneksi database bawaan; konfigurasi Laravel dapat diarahkan
  ke database lain dengan mengatur variabel `DB_*`.
- Python dengan PyTorch CPU, Transformers, Safetensors, dan Pillow untuk
  inferensi menggunakan model lokal
  `dima806/ai_vs_human_generated_image_detection`.
- JavaScript, Vite 7, Tailwind CSS 4, dan Laravel Vite Plugin untuk aset
  frontend.

## Prasyarat

Pada lingkungan pengembangan Windows yang digunakan proyek ini:

- PHP 8.2+ dengan ekstensi yang dibutuhkan Laravel, Composer, dan Node.js/npm
  yang kompatibel dengan Vite 7.
- Python 3.13 untuk environment Python yang digunakan dalam dokumentasi
  worker ini, serta akses jaringan saat dependency atau model pertama kali
  diunduh.
- PowerShell untuk contoh perintah di bawah.

Sesuaikan versi Python dengan versi yang memang tersedia dan diuji di mesin
Anda. Perintah `py -3.13` di bawah memerlukan Python 3.13 yang terdaftar pada
Python Launcher; jika belum terdaftar, gunakan path executable Python 3.13
yang terpasang atau daftarkan instalasinya. Model dan virtual environment tidak
disimpan di Git.

## Instalasi

Buka PowerShell di root repositori. Periksa versi PHP, Composer, Node/npm, dan
Python sebelum memulai:

```powershell
php --version
composer --version
node --version
npm --version
py --list
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

### Environment Python dan model

Ikuti panduan lengkap [ai-service/README.md](ai-service/README.md). Ringkasnya,
dari root repositori di PowerShell:

```powershell
py -3.13 -m venv ai-service\.venv
ai-service\.venv\Scripts\python.exe -m pip install -r ai-service\requirements.txt `
    --index-url https://download.pytorch.org/whl/cpu `
    --extra-index-url https://pypi.org/simple
ai-service\.venv\Scripts\python.exe ai-service\download_model.py
ai-service\.venv\Scripts\python.exe ai-service\predict.py --check-model
```

Unduhan model memerlukan akses ke Hugging Face. Inferensi aplikasi memakai
salinan lokal model, bukan Hugging Face Inference API.

## Konfigurasi worker lokal

Worker HTTP bersifat opsional. Dengan `AI_WORKER_ENABLED=false` (nilai bawaan
di `.env.example`), Laravel memanggil skrip Python sebagai subprocess; worker
terpisah tidak diperlukan. Untuk worker persisten, atur variabel server-side
`AI_WORKER_ENABLED=true`, `AI_WORKER_URL=http://127.0.0.1:8765`, timeout yang
sesuai, dan `AI_WORKER_SECRET`. Secret harus sama pada proses PHP dan proses
worker serta sekurang-kurangnya 32 byte UTF-8. Sediakan secret melalui
environment manager lokal yang sesuai; jangan tulis nilainya di README,
source control, frontend, atau log. Worker Python tidak membaca `.env`
Laravel secara otomatis.

Atur `AI_WORKER_SUBPROCESS_FALLBACK` secara sengaja. Default-nya `false`.
Jika fallback diaktifkan, subprocess hanya dipakai ketika pemeriksaan health
worker gagal; kegagalan atau timeout inferensi setelah request worker dikirim
tidak memulai classifier kedua.

Jalankan worker **secara manual** dari root repositori setelah model dan
private storage tersedia, di terminal yang mewarisi `AI_WORKER_SECRET`:

```powershell
if (-not $env:AI_WORKER_SECRET -or [Text.Encoding]::UTF8.GetByteCount($env:AI_WORKER_SECRET) -lt 32) {
    throw 'AI_WORKER_SECRET tidak tersedia atau kurang dari 32 byte.'
}
ai-service\.venv\Scripts\python.exe ai-service\worker.py
```

Worker hanya bind ke `127.0.0.1:8765`; worker ini tidak dipasang sebagai
Windows Service dan tidak otomatis mulai atau pulih setelah proses berhenti.
Lihat [ai-service/README.md](ai-service/README.md) untuk health check
terautentikasi, contoh panggilan manual, batasan, dan troubleshooting rinci.

## Menjalankan aplikasi

Setelah dependency terpasang, `.env` dikonfigurasi, dan migrasi selesai:

1. Jika `AI_WORKER_ENABLED=true`, mulai worker dengan langkah di atas dan
   biarkan terminalnya berjalan. Jika tidak, jalur subprocess memakai model
   lokal.
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
ditampilkan berdasarkan akun yang sedang masuk.

## Privasi dan batasan operasional

- Aplikasi memvalidasi gambar di server dan menyimpan file pada disk Laravel
  privat `image-analyses`; metadata dan hasil analisis disimpan di database.
- Endpoint gambar dan riwayat memeriksa kepemilikan analisis terhadap akun
  yang sedang masuk. File yang tidak lagi direferensikan dihapus saat item
  riwayat dihapus, jika pembersihan berhasil.
- Jalur inferensi lokal tidak mengirim gambar ke API inferensi eksternal.
  Pengunduhan dependency dan model pada saat setup tetap memerlukan jaringan
  eksternal.
- Worker menerima path file privat, bukan upload browser; membatasi akses ke
  direktori gambar aplikasi, memvalidasi format/ukuran gambar, memerlukan
  bearer authentication, dan hanya bind ke loopback. Jangan mengekspos port
  worker ke LAN atau internet.
- File gambar, database, log, dan backup dapat berisi data sensitif. Kebijakan
  retensi, backup, kontrol akses mesin, TLS/proxy, dan pengamanan deployment
  tetap menjadi tanggung jawab operator. Dokumentasi ini tidak menjamin
  keamanan deployment atau penghapusan backup/log di luar perilaku aplikasi.
- Worker hanya memproses satu inferensi pada satu waktu; permintaan sibuk,
  kelebihan beban, atau timeout dapat gagal. Worker harus dimulai dan
  dihentikan manual.

## Tes dan pemeriksaan

Jalankan dari root repositori:

```powershell
php artisan test --compact
npm run build
ai-service\.venv\Scripts\python.exe -m unittest discover -s ai-service\tests -v
git diff --check
```

Tes Python tersebut tidak membutuhkan unduhan model; tes inferensi nyata
memerlukan berkas model lokal.

## Troubleshooting

### Worker tidak aktif atau health check gagal

- Pastikan worker dijalankan di terminal terpisah dan selesai memuat model.
- Periksa `AI_WORKER_ENABLED`, `AI_WORKER_URL` (harus loopback
  `http://127.0.0.1:8765`), serta bahwa proses PHP dan Python mewarisi secret
  yang sama.
- Health endpoint memerlukan bearer authentication; secret yang kosong, tidak
  cocok, atau kurang dari 32 byte menyebabkan kegagalan.
- Periksa apakah port 8765 sedang dipakai. Periksa diagnostik worker dan
  Laravel, lalu mulai ulang proses yang berhenti. Jangan kirim secret ke
  browser atau membagikannya di log/chat.

### Model tidak ditemukan atau gagal dimuat

- Dari root repositori, jalankan kembali `ai-service\download_model.py`, lalu
  `ai-service\predict.py --check-model` menggunakan Python di virtual
  environment proyek.
- Pastikan file model ada di `ai-service\model\` dan instalasi dependency
  Python selesai tanpa error. Unduhan model memerlukan jaringan.
- Jika worker sudah hidup sebelum model diperbaiki, hentikan dengan `Ctrl+C`
  lalu mulai ulang worker.

### Konfigurasi salah

- Bandingkan nama variabel yang diperlukan dengan `.env.example` dan
  `config/services.php`; jangan menyalin secret nyata ke contoh atau repositori.
- Pastikan nilai database dan file SQLite benar sebelum migrasi. Untuk worker,
  cocokkan URL, toggle, timeout, dan secret di environment PHP dan Python.
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
- `.env` lokal: `APP_KEY`, `APP_URL`, `DB_*`, dan `AI_WORKER_*`. File ini
  lokal/rahasia, jangan disalin ke laporan atau source control.
- `config/database.php`, `config/services.php`, dan
  `config/filesystems.php`: database, pengaturan worker, dan lokasi storage
  privat.
- `ai-service/worker.py`, `ai-service/predict.py`,
  `ai-service/requirements.txt`, serta `ai-service/requirements-lock.txt`:
  startup worker, pemuatan model, dan versi dependency.
- `ai-service/model/`: artefak model yang diunduh, tidak disertakan di Git.
- `composer.json`/`composer.lock` dan `package.json`/`package-lock.json`:
  dependency serta perintah yang tersedia.

### Startup dari kondisi berhenti

1. Pastikan database yang benar tersedia, dependency terpasang, model lokal
   lolos `--check-model`, dan konfigurasi environment sudah disediakan.
2. Terapkan hanya migrasi yang belum berjalan dengan `php artisan migrate`.
3. Jika worker diaktifkan, mulai `worker.py` manual, lalu jalankan health
   check terautentikasi seperti di [panduan worker](ai-service/README.md).
4. Mulai `php artisan serve`; untuk mode pengembangan, mulai juga
   `npm run dev`. Untuk aset siap pakai, jalankan `npm run build`.
5. Masuk ke aplikasi dan lakukan satu unggahan uji yang tidak sensitif, lalu
   periksa hasil dan History. Hentikan worker dengan `Ctrl+C` saat selesai.

### Pemeriksaan sebelum demo atau deployment

- Pastikan environment target, database, migrasi, permission storage, dan
  backup yang diperlukan sudah diperiksa; jangan memakai perintah migrasi
  destruktif.
- Pastikan model dan versi dependency sesuai, build frontend berhasil, serta
  tes Laravel dan Python lulus pada environment target.
- Untuk worker aktif, verifikasi status `ready` dengan autentikasi, loopback
  binding, secret yang sama dan terlindungi, timeout, serta prosedur restart.
- Pastikan aplikasi dapat login, mengunggah format yang didukung, menampilkan
  prediksi dan riwayat akun, serta menghapus item uji.
- Jangan gunakan gambar pribadi tanpa izin untuk demo. Jangan menyajikan skor
  sebagai jaminan probabilitas, akurasi, atau bukti asal gambar.
- Deployment memerlukan peninjauan tersendiri atas TLS, pengelolaan secret,
  retensi data, backup, logging, kapasitas worker, dan kontrol akses host;
  checklist ini bukan sertifikasi keamanan.
