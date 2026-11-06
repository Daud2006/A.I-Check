# Activity Diagram A.I-Check

Dokumen ini menerjemahkan use case yang tersedia pada implementasi situs saat ini menjadi activity diagram terpisah per use case dan dikelompokkan berdasarkan aktor. Diagram menggunakan Mermaid `flowchart` dengan node awal/akhir, aktivitas, dan keputusan untuk merepresentasikan alur aktivitas UML.

## Aktor dan cakupan

- **Pengunjung**: belum masuk ke akun; dapat mengakses halaman publik, mendaftar, dan masuk.
- **Pengguna terdaftar**: dapat menjalankan fitur yang memerlukan autentikasi, termasuk analisis gambar, riwayat, dan profil.
- **Admin**: pengguna dengan peran `admin`. Admin juga dapat menggunakan use case pengguna terdaftar. Pada versi saat ini, area admin hanya menampilkan informasi; belum tersedia alat pengelolaan.
- **Layanan model AI lokal**: sistem eksternal terhadap alur web yang menerima gambar untuk menghasilkan prediksi.

Halaman deteksi dapat dibuka oleh pengunjung, tetapi pengiriman gambar membutuhkan login. Analisis hanya menerima JPEG, PNG, atau WebP maksimal 10 MB. Prediksi model bukan kepastian.

## Use case Pengunjung

### UC-01 — Melihat beranda

```mermaid
flowchart TD
    A([Mulai]) --> B[Pengunjung membuka beranda]
    B --> C[Sistem menampilkan halaman beranda]
    C --> D([Selesai])
```

### UC-02 — Melihat informasi tentang situs

```mermaid
flowchart TD
    A([Mulai]) --> B[Pengunjung membuka halaman Tentang]
    B --> C[Sistem menampilkan informasi situs]
    C --> D([Selesai])
```

### UC-03 — Membuka halaman deteksi

```mermaid
flowchart TD
    A([Mulai]) --> B[Pengunjung membuka halaman Checker]
    B --> C[Sistem menampilkan petunjuk format dan batas ukuran gambar]
    C --> D([Selesai])
```

### UC-03A — Mencoba mengunggah gambar tanpa masuk

```mermaid
flowchart TD
    A([Mulai]) --> B[Pengunjung memilih gambar yang didukung]
    B --> C[Pengunjung memilih Unggah Gambar]
    C --> D[Sistem menolak permintaan karena belum terautentikasi]
    D --> E[Sistem menampilkan pesan untuk masuk dan tautan Masuk]
    E --> F{Pengunjung memilih Masuk?}
    F -- Ya --> G[Sistem membuka halaman autentikasi]
    F -- Tidak --> H[Pengunjung tetap di halaman deteksi]
    G --> I([Selesai])
    H --> I
```

### UC-04 — Mendaftar akun

```mermaid
flowchart TD
    A([Mulai]) --> B[Pengunjung memilih Buat akun]
    B --> C[Pengunjung mengisi nama, email, kata sandi, dan konfirmasi]
    C --> D{Data valid dan email belum digunakan?}
    D -- Tidak --> E[Sistem menampilkan kesalahan validasi]
    E --> C
    D -- Ya --> F[Sistem menyimpan akun dengan peran pengguna]
    F --> G[Sistem memasukkan pengguna dan memperbarui sesi]
    G --> H[Sistem mengarahkan ke halaman tujuan atau beranda]
    H --> I([Selesai])
```

### UC-05 — Masuk ke akun

```mermaid
flowchart TD
    A([Mulai]) --> B[Pengunjung memilih Masuk]
    B --> C[Pengunjung mengisi email dan kata sandi]
    C --> D{Format input valid?}
    D -- Tidak --> E[Sistem menampilkan kesalahan validasi]
    E --> C
    D -- Ya --> F{Kredensial cocok?}
    F -- Tidak --> G[Sistem menampilkan pesan email atau kata sandi salah]
    G --> C
    F -- Ya --> H[Sistem membuat ulang sesi autentikasi]
    H --> I[Sistem mengarahkan ke halaman tujuan atau beranda]
    I --> J([Selesai])
```

## Use case Pengguna terdaftar

### UC-06 — Menganalisis gambar

```mermaid
flowchart TD
    A([Mulai]) --> B[Pengguna membuka halaman Checker]
    B --> C[Pengguna memilih atau menarik gambar ke area unggah]
    C --> D{Format JPEG, PNG, atau WebP dan ukuran maksimal 10 MB?}
    D -- Tidak --> E[Sistem menampilkan pesan validasi]
    E --> C
    D -- Ya --> F[Sistem menampilkan pratinjau gambar]
    F --> G[Pengguna memilih Unggah Gambar]
    G --> H[Sistem mengunggah dan menyimpan gambar secara privat]
    H --> I{Gambar tersimpan?}
    I -- Tidak --> J[Sistem menampilkan pesan kegagalan penyimpanan]
    J --> K([Selesai])
    I -- Ya --> L[Sistem mengubah status analisis menjadi diproses]
    L --> M[Layanan model AI lokal mengklasifikasikan gambar]
    M --> N{Klasifikasi berhasil?}
    N -- Tidak --> O[Sistem menandai analisis gagal dan menampilkan pesan]
    O --> K
    N -- Ya --> P[Sistem menyimpan label, skor, dan nama model]
    P --> Q[Sistem menampilkan prediksi dan catatan bahwa hasil bukan kepastian]
    Q --> K
```

### UC-07 — Melihat, mencari, memfilter, dan mengurutkan riwayat

```mermaid
flowchart TD
    A([Mulai]) --> B[Pengguna membuka Riwayat]
    B --> C[Sistem memuat analisis milik pengguna]
    C --> D[Sistem menampilkan daftar dan ringkasan statistik]
    D --> E{Pengguna mengubah pencarian, filter, atau urutan?}
    E -- Ya --> F[Sistem memfilter dan mengurutkan daftar]
    F --> G{Ada hasil yang cocok?}
    G -- Tidak --> H[Sistem menampilkan keadaan riwayat kosong atau tidak ditemukan]
    G -- Ya --> I[Sistem menampilkan daftar yang diperbarui]
    H --> E
    I --> E
    E -- Tidak --> J([Selesai])
```

### UC-08 — Melihat detail analisis dan gambar

```mermaid
flowchart TD
    A([Mulai]) --> B[Pengguna memilih salah satu item riwayat]
    B --> C[Sistem menampilkan label, skor, status, waktu, dan ID analisis]
    C --> D{Pratinjau gambar tersedia?}
    D -- Ya --> E[Sistem menampilkan gambar dari penyimpanan privat]
    D -- Tidak --> F[Sistem menampilkan pesan pratinjau tidak tersedia]
    E --> G[Pengguna menutup detail atau kembali ke daftar]
    F --> G
    G --> H([Selesai])
```

### UC-09 — Menyimpan laporan analisis

```mermaid
flowchart TD
    A([Mulai]) --> B[Pengguna membuka detail analisis]
    B --> C[Pengguna memilih Simpan laporan]
    C --> D[Sistem membuka dialog cetak browser]
    D --> E{Pengguna melanjutkan cetak atau simpan sebagai PDF?}
    E -- Ya --> F[Browser mencetak atau menyimpan laporan]
    E -- Tidak --> G[Pengguna membatalkan dialog]
    F --> H([Selesai])
    G --> H
```

### UC-10 — Menghapus satu riwayat analisis

```mermaid
flowchart TD
    A([Mulai]) --> B[Pengguna memilih hapus pada item riwayat]
    B --> C{Pengguna mengonfirmasi penghapusan?}
    C -- Tidak --> D[Sistem mempertahankan riwayat]
    C -- Ya --> E[Sistem menghapus analisis milik pengguna]
    E --> F{Gambar tidak lagi digunakan analisis lain?}
    F -- Ya --> G[Sistem mencoba menghapus file gambar privat]
    F -- Tidak --> I[Sistem mempertahankan file yang masih digunakan]
    G --> H{File berhasil dibersihkan?}
    H -- Ya --> J[Sistem memperbarui daftar riwayat]
    H -- Tidak --> K[Sistem memberi tahu bahwa file belum dapat dibersihkan]
    I --> J
    K --> J
    D --> L([Selesai])
    J --> L
```

### UC-11 — Menghapus seluruh riwayat analisis

```mermaid
flowchart TD
    A([Mulai]) --> B[Pengguna memilih Hapus semua]
    B --> C{Pengguna mengonfirmasi penghapusan?}
    C -- Tidak --> D[Sistem mempertahankan seluruh riwayat]
    C -- Ya --> E[Sistem menghapus seluruh analisis milik pengguna]
    E --> F[Sistem memeriksa file gambar yang tidak lagi digunakan]
    F --> G[Sistem mencoba membersihkan setiap file privat]
    G --> H{Semua file berhasil dibersihkan?}
    H -- Ya --> I[Sistem menampilkan daftar riwayat kosong]
    H -- Tidak --> J[Sistem memberi tahu bahwa sebagian file belum dapat dibersihkan]
    J --> K[Sistem menampilkan daftar riwayat kosong]
    D --> L([Selesai])
    I --> L
    K --> L
```

### UC-12 — Melihat dan memperbarui profil

```mermaid
flowchart TD
    A([Mulai]) --> B[Pengguna membuka Profil]
    B --> C[Sistem menampilkan nama dan email saat ini]
    C --> D[Pengguna mengubah nama atau email]
    D --> E{Nama dan email valid serta email tidak dipakai akun lain?}
    E -- Tidak --> F[Sistem menampilkan kesalahan validasi]
    F --> D
    E -- Ya --> G{Email berubah?}
    G -- Ya --> H[Sistem menghapus status verifikasi email]
    G -- Tidak --> I[Sistem menyimpan perubahan profil]
    H --> I
    I --> J[Sistem menampilkan pesan profil berhasil diperbarui]
    J --> K([Selesai])
```

### UC-13 — Keluar dari akun

```mermaid
flowchart TD
    A([Mulai]) --> B[Pengguna memilih Keluar]
    B --> C[Sistem mengakhiri autentikasi]
    C --> D[Sistem membatalkan sesi dan memperbarui token CSRF]
    D --> E[Sistem mengarahkan ke beranda]
    E --> F([Selesai])
```

## Use case Admin

### UC-14 — Membuka panel admin

```mermaid
flowchart TD
    A([Mulai]) --> B[Admin membuka /admin]
    B --> C{Admin sudah masuk?}
    C -- Tidak --> D[Sistem meminta autentikasi]
    C -- Ya --> E{Peran akun adalah admin?}
    E -- Tidak --> F[Sistem menolak akses]
    E -- Ya --> G[Sistem menampilkan panel admin]
    G --> H[Sistem menampilkan informasi bahwa alat pengelolaan belum tersedia]
    D --> I([Selesai])
    F --> I
    H --> I
```

## Catatan implementasi

- Diagram ini mencakup use case yang terlihat pada rute, controller, dan halaman aplikasi saat ini; diagram tidak mengasumsikan fitur admin yang belum tersedia.
- Jika pengunjung mencoba mengunggah gambar, API mengembalikan status belum terautentikasi dan halaman menawarkan tautan masuk.
- Validasi masukan, kegagalan penyimpanan, dan kegagalan klasifikasi ditampilkan sebagai cabang alternatif pada diagram terkait.
- Rujukan implementasi: `routes/web.php`, `app/Http/Controllers/`, `resources/views/`, dan `resources/js/modules/`.
