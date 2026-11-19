# A.I-Check — konteks proyek

Audit lokal pada 19 November 2026. A.I-Check adalah aplikasi akademik untuk memperkirakan apakah gambar atau poster dibuat atau diedit dengan AI. Antarmuka utama menggunakan bahasa Indonesia. Skor model adalah estimasi dan bisa keliru.

## Teknologi dan struktur

| Lokasi | Peran |
| --- | --- |
| `routes/web.php`, `bootstrap/app.php`, `app/Http/Controllers/` | Rute, sesi, autentikasi, otorisasi, dan pengendali halaman/API Laravel |
| `app/Services/`, `app/Jobs/`, `app/Console/Commands/` | Detektor lokal, kuota, Midtrans, pekerjaan analisis, dan worker antrean |
| `app/Models/`, `database/migrations/`, `database/seeders/` | Eloquent, skema domain, dan data awal pengembangan |
| `resources/views/`, `resources/js/`, `resources/css/`, `public/` | Blade, JavaScript, CSS, dan aset publik |
| `config/`, `.env.example`, `composer.json`, `package.json` | Pengaturan aplikasi dan dependensi |
| `tests/`, `phpunit.xml` | Tes unit dan fitur Laravel |
| `aidetect-engine/` | Repositori Python terpisah yang diabaikan Git utama. Berisi FastAPI, konfigurasi model, lingkungan virtual, cache bobot, dan tes |
| `ai-service/` | Direktori lama dengan lingkungan virtual dan artefak Python. Hanya `.gitignore` tercatat di Git utama. Integrasi Laravel saat ini mengarah ke API detektor loopback |

Laravel 12 (`v12.69.3` di lockfile), PHP minimal 8.2, Composer, Blade, Vite 7, Tailwind CSS 4, dan SweetAlert2. PHP Laragon 8.3.30 tersedia dan memiliki `pdo_sqlite`, `fileinfo`, `gd`, dan `mbstring`. PHP 8.2.20 juga tersedia. Perintah `php` belum tersedia di PATH PowerShell audit. Node.js 24.22.1 dan npm 11.19.1 tersedia. Engine memiliki lingkungan virtual Python 3.12.5 dengan FastAPI, Uvicorn, dan PyTorch. Versi paket terpasang berbeda dari pin di berkas requirements, sehingga reproduksi lingkungan perlu diperiksa sebelum memasang ulang.

## Arsitektur website dan alur Deteksi Gambar

1. Pengunjung dapat membuka `/checker`. Unggah, status, dan gambar hasil memerlukan sesi login. Laravel menerima JPEG, PNG, dan WebP hingga 10 MB, lalu menyimpan file pada disk privat `image-analyses`.
2. `CheckerController` membuat `ImageAnalysis` berstatus `pending` dan mereservasi kuota pada transaksi database. Job dikirim ke antrean `premium` atau `default`. Worker `analyses:work` memerlukan driver `database` dan memberi bobot hingga tiga job Premium sebelum melayani antrean Free saat keduanya berisi pekerjaan.
3. `ClassifyImageAnalysis` menandai `processing`. `LocalAiDetector` memanggil `GET /health`, memeriksa ensemble dan lisensi melalui `GET /info`, lalu mengirim satu gambar lewat multipart `files` ke `POST /detect`. Host dibatasi ke HTTP `127.0.0.1` dan redirect dinonaktifkan.
4. Laravel memvalidasi kontrak JSON, menyimpan label, `p_ai` pada `confidence_score`, nama model, serta rincian prediksi. Nilai `p_ai` tetap merupakan Probabilitas AI untuk setiap label. Jika gagal, job mengosongkan prediksi, mencatat kegagalan, dan melepaskan reservasi kuota.
5. UI memantau status berdasarkan ID. Riwayat menampilkan data tersimpan milik akun, termasuk waktu, label, probabilitas, dan rincian yang tersedia. Data lama tanpa rincian mendapat keterangan khusus. Penghapusan satu atau semua item dibatasi ke pemilik dan membersihkan file yang sudah tidak dirujuk.

## AI Engine

Backend yang diwajibkan oleh kode Laravel adalah `simple_ai_image_detector` dengan ensemble `clip-probe`, `commforensics224`, `commforensics384`, dan `haywoodsloan`. Konfigurasi yang cocok ditemukan di `aidetect-engine/models_permissive` (`fusion.json` dan `probe.json`). Default internal Python mengarah ke `models/`, sehingga perintah startup perlu memilih `models_permissive` secara eksplisit. Kandidat historis `dima806/ai_vs_human_generated_image_detection` tidak muncul dalam kontrak Laravel yang diperiksa.

FastAPI memuat `Detector.load` saat startup dan menyimpan detektor dalam memori. Endpoint inferensi menyerialkan pekerjaan per proses dengan lock. Cache Hugging Face tersedia, termasuk direktori untuk kedua model CommForensics, Haywoodsloan, model fitur timm, dan Organika. Keberadaan cache tidak membuktikan semua bobot lengkap atau siap dimuat. Git utama juga tidak merekam revisi engine, bobot, atau cache tersebut.

API dapat mengembalikan `AI-GENERATED`, `AI-EDITED`, `REAL`, dan `UNCERTAIN`; `ERROR` ditolak Laravel sebagai hasil prediksi. Metadata provenance diproses dari file secara default dan dapat memengaruhi **verdict** pada penanda kuat. Skor `p_ai` tetap berasal dari ensemble piksel. Konfigurasi lokal mendokumentasikan `fpr5`, tetapi Laravel saat ini mengikuti operating point API, menerima empat operating point yang dikenali, dan memeriksa bahwa `fpr5` tersedia di `/info`. Proses runtime dan prediksi sesungguhnya perlu diverifikasi secara terpisah.

## Database

`database/database.sqlite` tersedia dan diperiksa melalui SQLite dalam mode baca-saja. Lima migrasi tercatat, termasuk penambahan `prediction_details` yang nullable. Ada tiga analisis selesai tanpa rincian tersebut. Pemeriksaan hanya mengambil skema dan jumlah agregat. Koneksi yang digunakan proses aplikasi lokal belum dikonfirmasi karena konfigurasi privat tidak dibuka. `.env.example` menetapkan SQLite serta driver database untuk sesi, cache, dan antrean.

| Domain | Tabel dan relasi |
| --- | --- |
| Akun | `users`, `sessions`, `password_reset_tokens`. Pengguna memiliki analisis, penggunaan, langganan, dan pembayaran |
| Analisis | `image_analyses` merujuk pengguna. `usage_records` merujuk pengguna dan analisis. FK ke analisis menjadi null saat analisis dihapus agar catatan kuota tetap ada |
| Billing | `plans` memiliki banyak `subscriptions`. `payments` merujuk pengguna dan dapat merujuk langganan. ID transaksi provider memiliki batasan keunikan gabungan |
| Operasional | `jobs`, `job_batches`, `failed_jobs`, `cache`, dan `cache_locks` |

## Status fitur

| Fitur | Implementasi dan cakupan |
| --- | --- |
| Beranda dan Tentang | Rute dan halaman tersedia untuk publik |
| Akun dan profil | Registrasi, login, logout, hash password, regenerasi sesi, reset melalui kode email, dan edit nama. Email profil hanya baca. Pengiriman email sungguhan belum diverifikasi |
| Deteksi Gambar | Validasi, penyimpanan privat, antrean, polling, status gagal, dan penyimpanan prediksi terimplementasi. Inferensi dengan bobot asli dan alur browser belum diverifikasi |
| Riwayat | Data berdasarkan pemilik, pencarian, filter, pengurutan, modal, cetak laporan, serta hapus satu atau semua item tersedia di kode |
| Free dan Premium | Free lima analisis per tujuh hari berjalan, tanpa kuota tamu karena unggah membutuhkan login. Premium memakai batas paket dalam periode yang sama; nilai null berarti tanpa batas. Admin tanpa batas. Hak Premium memerlukan langganan yang berlaku, paket berbayar aktif, dan pembayaran berstatus `paid` |
| Billing | Katalog, checkout Midtrans Snap, transaksi privat, validasi tanda tangan dan jumlah pembayaran pada webhook, serta perubahan status langganan. Integrasi Midtrans langsung belum diverifikasi |
| Admin | Dasbor, daftar dan detail pengguna/analisis/transaksi, pengelolaan paket, dan laporan CSV. Rute memerlukan login, dengan pemeriksaan role di controller. Pengelolaan pengguna yang terlihat di rute berupa daftar/detail |
| Visualisasi area | Belum ditemukan pada alur deteksi atau riwayat. Rincian yang tersedia berupa skor per model, ambang, basis, dan penanda metadata |

## Konfigurasi dan cara menjalankan

Jalankan dari folder proyek melalui Laragon Terminal dengan profil PowerShell, atau PowerShell yang sudah mengenali PHP Laragon, Composer, dan Node. Administrator tidak diperlukan untuk perintah startup ini. Pastikan konfigurasi lokal memiliki `APP_KEY`, `APP_URL`, database yang benar, `QUEUE_CONNECTION=database`, dan akses tulis untuk storage serta cache. Mailer pengiriman dan `MIDTRANS_*` diperlukan untuk fitur terkait. Gunakan `.env.example` sebagai daftar variabel dan simpan kredensial di konfigurasi lokal.

Di terminal tersendiri, gunakan lingkungan virtual dan cache yang sudah tersedia.

```powershell
$detectorPython = Resolve-Path .\aidetect-engine\.venv-aidetect\Scripts\python.exe
$env:HF_HOME = (Resolve-Path .\aidetect-engine\.hf_cache).Path
$env:HF_HUB_OFFLINE = '1'
$env:AIDETECT_MODEL_DIR = (Resolve-Path .\aidetect-engine\models_permissive).Path
$env:AIDETECT_OPERATING_POINT = 'fpr5'
$env:AIDETECT_THREADS = '4'
$env:AIDETECT_MAX_BYTES = '10485760'
$env:AIDETECT_MAX_FILES = '1'
& $detectorPython -m uvicorn aidetect.api:app --app-dir .\aidetect-engine --host 127.0.0.1 --port 8766 --workers 1
```

Periksa endpoint lokal dengan `Invoke-RestMethod http://127.0.0.1:8766/health` dan `Invoke-RestMethod http://127.0.0.1:8766/info`. Kesiapan berarti status `ok`, `models_loaded` benar, dan ensemble sesuai. Contoh Laravel menggunakan `AI_DETECTOR_URL=http://127.0.0.1:8766`, timeout koneksi 5 detik, pemeriksaan health/info masing-masing 5 detik, dan inferensi 65 detik.

Di terminal proyek lain, jalankan `composer run dev` untuk memulai `php artisan serve`, `php artisan analyses:work`, dan Vite. Buka `http://127.0.0.1:8000`. Jika website sudah disajikan oleh Laragon, gunakan website tersebut dan jalankan worker tunggal secara terpisah bila belum berjalan. Vite dapat dijalankan dengan `npm run dev` atau gunakan aset dari `npm run build`. Sebelum menerapkan migrasi yang diperlukan pada lingkungan lain, verifikasi koneksi dan status migrasinya.

## Pengujian

Untuk pemeriksaan berikutnya gunakan `php artisan test`, `php artisan route:list`, `npm run build`, dan `git diff --check`. Untuk Python gunakan `python -m pytest` dari `aidetect-engine` dengan lingkungan virtualnya. Periksa penanda tes yang memerlukan bobot sebelum memilih pengujian penuh. Uji browser terpisah dapat memakai satu gambar non-sensitif dan membandingkan Hasil Deteksi dengan Riwayat.

Hasil audit: 38 rute Laravel non-vendor terdaftar, 65 berkas PHP lolos lint, dan PHPUnit lulus 117 tes dengan 792 asersi. Tes Laravel dijalankan di salinan sementara dengan SQLite dalam memori dan tanpa membuka konfigurasi privat proyek. Pengujian deteksi dan pembayaran menggunakan simulasi. Build Vite 7.3.6 berhasil dengan output sementara, dan `git diff --check` bersih. Python lulus 255 tes di luar `tests/test_api.py`. Suite API mengumpulkan 13 kasus, tetapi tertahan pada tes pertama (`test_health_reports_ready`) dan dihentikan. Penyebabnya belum dipastikan. Pada pemeriksaan port, tidak teramati listener di port default 8766. Engine dengan bobot asli, alur browser, worker nyata, pengiriman OTP, dan Midtrans langsung belum diverifikasi.

## Git, risiko, dan bagian yang perlu dijaga

- Git utama mulai bersih di `Daud` pada `c4605a6`, sama dengan referensi lokal `origin/Daud`. Branch lokal lain `main`; referensi remote `origin/Bakeri`, `origin/Daud`, dan `origin/Main`. `origin/HEAD` menunjuk `origin/Daud`. Status remote ditentukan lewat referensi lokal.
- Engine memiliki Git sendiri, branch `main` pada `18a5996`, dengan perubahan sebelumnya pada `.gitignore` dan `tests/test_imageio.py`. Pertahankan kedua perubahan tersebut.
- README bagian startup website melewatkan worker analisis. `baca.txt` dan skrip `composer run dev` memperlihatkan alur dengan worker. README juga menyebut skor kelas, sementara kode menyajikan `p_ai` sebagai Probabilitas AI.
- PHP 8.3.30 Laragon mengeluarkan peringatan bahwa modul `mysqli` sudah dimuat. Pemeriksaan Laravel tetap lulus dengan SQLite.
- PHP Windows yang diperiksa tidak memuat `pcntl`, sedangkan timer timeout worker Laravel bergantung pada ekstensi itu. Dengan timeout job 80 detik dan `retry_after` default 90 detik, pengawasan perlu mengandalkan batas waktu HTTP dan verifikasi perilaku worker nyata. Penguncian SQLite saat beberapa unggahan bersamaan juga belum diuji.
- Fusi permissive dikalibrasi pada `bfloat16`. Pemeriksaan kemampuan `bfloat16` membaca `/proc/cpuinfo` dan pada Windows biasa jatuh ke `float32`. Engine memperingatkan jika presisi runtime berbeda dari kalibrasi. Dampaknya pada skor dan label lokal belum diukur.
- Pertahankan disk privat, pemeriksaan pemilik dan admin, kontrak API, pemetaan label, ambang dan semantik skor, detail historis, pencatatan kuota, serta validasi webhook. Perubahan skema, billing, atau model memerlukan evaluasi dan persetujuan pemilik.

## Berkas yang diperiksa

- `README.md`, `baca.txt`, `docs/activity-diagrams.md`, `.gitignore`, `.env.example`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `phpunit.xml`, `routes/web.php`.
- Controller di `app/Http/Controllers/` untuk akun, profil, checker, riwayat, billing, webhook, dan admin. Definisi relasi di `app/Models/`, migrasi di `database/migrations/`, serta skema SQLite lokal.
- `app/Services/LocalAiDetector.php`, `AnalysisQuotaService.php`, `MidtransNotificationProcessor.php`, `app/Jobs/ClassifyImageAnalysis.php`, `app/Console/Commands/WeightedAnalysisQueueWorker.php`, `config/services.php`, `config/filesystems.php`, `config/queue.php`, `resources/js/modules/checker.js`, `resources/js/modules/history.js`, dan cakupan tes di `tests/`.
- `aidetect-engine/aidetect/api.py`, `detector.py`, `backends.py`, `runtime.py`, `models_permissive/fusion.json`, inventaris `probe.json` dan cache bobot, `pyproject.toml`, `requirements.txt`, `requirements-api.txt`, `tests/`, serta inventaris `ai-service/`.
