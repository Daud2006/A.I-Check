<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A.I Check - pemeriksaan visual gambar berbasis AI.">
    <title>@yield('title', 'A.I Check')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="kerangka-situs">
        {{-- Bagian header digunakan untuk menampilkan navigasi utama yang dapat diakses di seluruh halaman. --}}
        <header class="bagian-header">
            <div class="container isi-header">
                <a href="{{ route('home') }}" class="logo-website" aria-label="A.I Check">
                    <span class="tanda-logo"><x-icon name="spark" /></span>
                    <span>A.I <span class="aksen-logo">Check</span></span>
                </a>

                <nav class="menu-navigasi" aria-label="Navigasi utama">
                    <a href="{{ route('home') }}" class="tautan-menu {{ request()->routeIs('home') ? 'is-active' : '' }}">Beranda</a>
                    <a href="{{ route('checker') }}" class="tautan-menu {{ request()->routeIs('checker') ? 'is-active' : '' }}">Deteksi Poster</a>
                    <a href="{{ route('history') }}" class="tautan-menu {{ request()->routeIs('history') ? 'is-active' : '' }}">Riwayat</a>
                    <a href="{{ route('about') }}" class="tautan-menu {{ request()->routeIs('about') ? 'is-active' : '' }}">Tentang</a>
                </nav>

                <div class="aksi-header">
                    <button type="button" class="btn btn-primary" data-auth-open="login">Masuk</button>
                </div>

                <button type="button" class="tombol-menu-mobile" id="pembuka-menu-mobile" aria-label="Buka menu" aria-expanded="false">
                    <x-icon name="menu" />
                </button>
            </div>

            <div class="menu-mobile" id="menu-mobile">
                <a href="{{ route('home') }}" class="tautan-menu-mobile">Beranda</a>
                <a href="{{ route('checker') }}" class="tautan-menu-mobile">Deteksi Poster</a>
                <a href="{{ route('history') }}" class="tautan-menu-mobile">Riwayat</a>
                <a href="{{ route('about') }}" class="tautan-menu-mobile">Tentang</a>
                <button type="button" class="btn btn-primary btn-full" data-auth-open="login">Masuk</button>
            </div>
        </header>

        <main class="konten-utama">
            @yield('content')
        </main>

        {{-- Bagian footer menampilkan informasi dasar dan sumber model yang digunakan. --}}
        <footer class="bagian-footer">
            <div class="container isi-footer">
                <div class="logo-website logo-footer"><span class="tanda-logo"><x-icon name="spark" /></span>A.I Check</div>
                <p>© 2025 A.I Check. Hasil deteksi bersifat estimasi, bukan keputusan mutlak.</p>
                <span class="daya-footer"><span class="titik-daya"></span>Powered by Hugging Face</span>
            </div>
        </footer>
    </div>

    {{-- Modal otorisasi berfungsi untuk login dan pendaftaran pengguna dengan satu tampilan reusable. --}}
    <div class="modal-otorisasi" id="modal-otorisasi" aria-hidden="true">
        <div class="panel-otorisasi" role="dialog" aria-modal="true" aria-labelledby="judul-modal">
            <div class="kepala-modal">
                <span class="ikon-modal" id="ikon-modal"><x-icon name="lock" /></span>
                <button type="button" class="tombol-tutup-modal" data-auth-close aria-label="Tutup"><x-icon name="close" /></button>
            </div>

            <h2 class="judul-modal" id="judul-modal">Selamat datang kembali</h2>
            <p class="deskripsi-modal" id="deskripsi-modal">Masuk untuk menyimpan dan menyinkronkan riwayat analisis.</p>

            <form class="formulir-otorisasi" id="formulir-masuk">
                <label class="kolom-formulir">
                    <span class="label-formulir">Email</span>
                    <input class="form-control" type="email" name="email" required placeholder="nama@email.com">
                </label>
                <label class="kolom-formulir">
                    <span class="label-formulir">Kata sandi</span>
                    <input class="form-control" type="password" name="password" required minlength="8" placeholder="Minimal 8 karakter">
                </label>
                <div class="baris-formulir">
                    <label class="cek-formulir"><input type="checkbox" checked> Ingat saya</label>
                    <button type="button" class="tautan-formulir" style="border:0;background:none;">Lupa kata sandi?</button>
                </div>
                <button class="btn btn-primary btn-full" type="submit">Masuk</button>
            </form>

            <form class="formulir-otorisasi" id="formulir-daftar" hidden>
                <label class="kolom-formulir"><span class="label-formulir">Nama lengkap</span><input class="form-control" type="text" name="name" required minlength="3" placeholder="Masukkan nama lengkap"></label>
                <label class="kolom-formulir"><span class="label-formulir">Alamat email</span><input class="form-control" type="email" name="email" required placeholder="nama@email.com"></label>
                <label class="kolom-formulir"><span class="label-formulir">Kata sandi</span><input class="form-control" type="password" name="password" required minlength="8" placeholder="Minimal 8 karakter"></label>
                <label class="kolom-formulir"><span class="label-formulir">Konfirmasi kata sandi</span><input class="form-control" type="password" name="confirmPassword" required minlength="8" placeholder="Ulangi kata sandi"></label>
                <label class="cek-formulir"><input type="checkbox" required> Saya menyetujui Syarat Penggunaan dan Kebijakan Privasi A.I Check.</label>
                <button class="btn btn-primary btn-full" type="submit">Buat akun</button>
            </form>

            <div class="bagian-bawah-modal">
                <span id="teks-bawah-modal">Belum punya akun?</span>
                <button type="button" id="pengalih-mode-otorisasi" data-auth-mode="register">Daftar gratis</button>
            </div>
        </div>
    </div>
</body>
</html>
