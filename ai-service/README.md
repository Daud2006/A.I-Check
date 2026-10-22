# POC Klasifikasi Gambar AI Lokal

POC terisolasi ini memuat model `dima806/ai_vs_human_generated_image_detection`
dari `ai-service/model/` dan menjalankan inferensi menggunakan CPU. Skrip
hanya menggunakan berkas model lokal dan gambar lokal yang diberikan. Gambar
tidak dikirim ke API inferensi atau layanan eksternal.

## Persiapan

Jalankan perintah berikut dari root repositori di PowerShell:

```powershell
py -3.13 -m venv ai-service\.venv
ai-service\.venv\Scripts\python.exe -m pip install -r ai-service\requirements.txt `
    --index-url https://download.pytorch.org/whl/cpu `
    --extra-index-url https://pypi.org/simple
ai-service\.venv\Scripts\python.exe ai-service\download_model.py
```

`requirements-lock.txt` mencatat versi dependensi yang berhasil dipasang untuk
POC ini.

Periksa pemuatan model dan label yang dikonfigurasi:

```powershell
ai-service\.venv\Scripts\python.exe ai-service\predict.py --check-model
```

Jalankan prediksi untuk gambar lokal:

```powershell
ai-service\.venv\Scripts\python.exe ai-service\predict.py "C:\path\ke\gambar.jpg"
```

Laravel menjalankan skrip yang sama dengan opsi `--json`. Jika berhasil, skrip
menghasilkan satu objek JSON berisi `label` (`human` atau `ai_generated`),
`score`, `model`, dan `inference_time_ms`. Skor adalah probabilitas softmax
model untuk kelas yang diprediksi; skor bukan jaminan bahwa prediksi benar dan
bukan ukuran kepastian dunia nyata yang telah dikalibrasi. Waktu inferensi
tidak mencakup waktu startup dan pemuatan model.

Pemetaan `id2label` dibaca dari konfigurasi model yang diunduh. Label tidak
ditentukan dari nama berkas atau metadata gambar. Unduhan model dan virtual
environment tidak disertakan dalam version control.

## Integrasi Laravel dengan subprocess

Jika worker dinonaktifkan (konfigurasi bawaan), Laravel menjalankan skrip
sebagai proses anak menggunakan Python dari `ai-service/.venv/` dan path gambar
dari record analisis privat milik pengguna. Pada jalur bawaan ini tidak ada
layanan Python HTTP yang berjalan dan tidak ada port publik yang dibuka.

Jalankan Laravel secara lokal dengan `php artisan serve`, masuk ke aplikasi,
lalu gunakan Image Checker yang sudah tersedia. Setiap analisis membuat proses
Python baru dan memuat model lokal; batas waktu subprocess adalah 60 detik.

## Worker lokal persisten

Worker memuat processor dan model satu kali saat startup, hanya bind ke
`127.0.0.1:8765`, dan memerlukan bearer secret untuk kedua endpoint. Root gambar
yang diizinkan hanya `storage/app/private/image-analyses` milik Laravel.
Request inferensi hanya menerima field `path`. Worker memvalidasi canonical
path, menolak traversal dan path atau symlink yang keluar dari root tersebut,
memeriksa ekstensi serta format gambar sebenarnya, dan membatasi ukuran gambar
hingga 10 MiB atau 40 megapiksel.

Benchmark lokal sebelumnya mencatat startup dingin sekitar 25 detik pada
komputer pengujian. Tunggu hingga worker selesai memuat model sebelum
mengirim analisis pertama.

### Menjalankan worker

Pastikan `AI_WORKER_SECRET` yang sama sudah tersedia di Windows user
environment. Jangan membuat secret baru ketika worker dimulai ulang. Dari
terminal PowerShell khusus, buka root repositori dan jalankan:

```powershell
if (-not $env:AI_WORKER_SECRET -or [Text.Encoding]::UTF8.GetByteCount($env:AI_WORKER_SECRET) -lt 32) {
    throw 'AI_WORKER_SECRET tidak tersedia atau tidak valid pada sesi PowerShell ini.'
}
ai-service\.venv\Scripts\python.exe ai-service\worker.py
```

Biarkan terminal worker tetap terbuka. Jangan tampilkan secret, menyimpannya di
source control, atau menuliskannya ke dokumentasi. Secret harus sudah
dikonfigurasi di Windows user environment atau disediakan dengan aman oleh
pengelola proses.

### Memeriksa health worker

Buka terminal PowerShell kedua. Pemeriksaan ini menggunakan secret dari
environment tanpa mencetak nilainya:

```powershell
$headers = @{ Authorization = "Bearer $env:AI_WORKER_SECRET" }
Invoke-RestMethod -Uri http://127.0.0.1:8765/health -Headers $headers
```

Respons yang diharapkan memiliki `status` bernilai `ready` dan nama model
lokal. Health check ini memerlukan autentikasi.

### Mengirim request inferensi manual

Untuk pengujian manual saja, path harus merujuk ke gambar yang sudah berada di
private storage aplikasi. Ganti contoh path dengan berkas privat yang ada:

```powershell
$headers = @{ Authorization = "Bearer $env:AI_WORKER_SECRET" }
$body = @{ path = (Resolve-Path "storage\app\private\image-analyses\users\1\image.png").Path } | ConvertTo-Json
Invoke-RestMethod -Uri http://127.0.0.1:8765/infer -Method Post -Headers $headers -ContentType "application/json" -Body $body
```

Jangan gunakan endpoint ini untuk path di luar private storage. Browser tidak
boleh mengirim path berkas arbitrer ke worker.

### Menghentikan worker dan penanganan kegagalan

Hentikan worker dengan menekan `Ctrl+C` di terminal worker. Worker tidak
dipasang sebagai Windows Service, tidak berjalan otomatis saat komputer
dimulai, dan tidak memulai ulang dirinya sendiri setelah crash. Jika startup
atau pemuatan model gagal, worker berhenti dengan diagnostik yang aman;
periksa konfigurasi dan ketersediaan model, lalu jalankan kembali secara
manual.

Jika inferensi melewati batas waktu, worker mengembalikan HTTP 504. Pemanggilan
PyTorch yang sedang berjalan tidak dapat dihentikan dengan aman, sehingga slot
inferensi tetap terpakai sampai proses selesai atau worker dihentikan.
Permintaan inferensi lain saat slot tersebut terpakai akan menerima HTTP 429.
Worker hanya menjalankan satu inferensi model pada satu waktu dan membatasi
jumlah thread penangan request serta antrean koneksi.

Worker tidak menerima request langsung dari browser. Jangan bind atau
meneruskannya ke jaringan LAN maupun internet. Binding loopback tetap
dilengkapi bearer authentication karena proses lokal lain juga dapat mencoba
mengakses port tersebut.

## Integrasi worker dengan Laravel

Integrasi worker bersifat opsional dan tetap hanya untuk komunikasi
server-ke-server. Route, UI, dan payload browser tidak berubah. Atur variabel
berikut di environment server-side yang diwarisi proses PHP:

```text
AI_WORKER_ENABLED=true
AI_WORKER_URL=http://127.0.0.1:8765
AI_WORKER_SECRET=<secret worker yang sama>
AI_WORKER_CONNECT_TIMEOUT=2
AI_WORKER_HEALTH_TIMEOUT=3
AI_WORKER_TIMEOUT=50
AI_WORKER_SUBPROCESS_FALLBACK=false
```

Secret yang sudah ada tersimpan di Windows user environment. Atur variabel
`AI_WORKER_*` lainnya melalui Windows Environment Variables, lalu mulai ulang
Laragon agar proses PHP mewarisi konfigurasi terbaru. Laravel membaca secret
hanya di server; secret tidak dikirim ke browser atau ditulis ke log.
`AI_WORKER_SECRET` harus memiliki panjang minimal 32 byte.

Laravel membatasi URL worker ke `http://127.0.0.1`. Batas waktu request worker
bawaannya 50 detik, lebih pendek dari batas request browser yang ada, yaitu
75 detik. Health check dibatasi 3 detik. Jika health check gagal dan fallback
subprocess aktif, pemrosesan server dapat memerlukan hingga sekitar 63 detik
setelah upload diterima; upload yang lambat tetap dapat melampaui batas waktu
browser.

Dengan worker nonaktif (konfigurasi bawaan), Laravel tetap menggunakan
subprocess Python terisolasi. Jika worker aktif, Laravel memeriksa endpoint
health yang terautentikasi terlebih dahulu. Subprocess hanya digunakan jika
health check gagal dan `AI_WORKER_SUBPROCESS_FALLBACK=true`. Setelah request
inferensi dikirim, HTTP error atau timeout tidak memicu subprocess kedua
karena worker mungkin masih memproses gambar tersebut. Jika fallback nonaktif
dan worker tidak tersedia, analisis ditandai gagal dan aplikasi mengembalikan
pesan aman.

Jalankan worker sebelum memakai Image Checker dan hentikan dengan `Ctrl+C`
setelah selesai. Tidak ada startup atau restart otomatis. Setelah mengubah
environment, mulai ulang PHP/Laragon. Jika aplikasi menggunakan cache
konfigurasi Laravel, perbarui cache tersebut melalui prosedur lokal yang
berlaku.

Jalankan tes worker tanpa mengunduh model:

```powershell
ai-service\.venv\Scripts\python.exe -m unittest discover -s ai-service\tests -v
```

## Lisensi dan keterbatasan

Repositori model menyatakan artefak model menggunakan lisensi Apache-2.0.
Pernyataan tersebut tidak menetapkan lisensi data pelatihan atau setiap
dependensi perangkat lunak transitif. Model card memperingatkan adanya concept
drift dan memuat hasil evaluasi dari pemilik model; hasil tersebut bukan
benchmark independen. Perlakukan keluaran sebagai prediksi probabilistik,
bukan bukti.
