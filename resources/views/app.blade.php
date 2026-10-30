<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A.I Check - pemeriksaan visual gambar berbasis AI.">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'A.I Check')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body data-logout-success="{{ session()->pull('logout_success', false) ? 'true' : 'false' }}">
    <div class="kerangka-situs">
        {{-- Header --}}
        <header class="bagian-header">
            <div class="container isi-header">
                <a href="{{ route('home') }}" class="logo-website" aria-label="A.I Check">
                    <span class="tanda-logo">@include('icon', ['name' => 'spark'])</span>
                    <span>A.I <span class="aksen-logo">Check</span></span>
                </a>

                <nav class="menu-navigasi" aria-label="Navigasi utama">
                    <a href="{{ route('home') }}"
                        class="tautan-menu {{ request()->routeIs('home') ? 'is-active' : '' }}">Beranda</a>
                    <a href="{{ route('checker') }}"
                        class="tautan-menu {{ request()->routeIs('checker') ? 'is-active' : '' }}">Deteksi Poster</a>
                    <a href="{{ route('history') }}"
                        class="tautan-menu {{ request()->routeIs('history') ? 'is-active' : '' }}">Riwayat</a>
                    <a href="{{ route('about') }}"
                        class="tautan-menu {{ request()->routeIs('about') ? 'is-active' : '' }}">Tentang</a>
                </nav>

                <div class="aksi-header">
                    @if (request()->routeIs('home'))
                    <button type="button" class="btn btn-subscribe" data-subscribe-open>
                        Subscriptions
                    </button>
                    @endif

                    @auth
                    <a href="{{ route('profile') }}" class="btn btn-secondary">{{ auth()->user()->name }}</a>
                    <form method="POST" action="{{ route('logout') }}" data-confirm-logout>
                        @csrf
                        <button type="submit" class="btn btn-primary">Keluar</button>
                    </form>
                    @else
                    <button type="button" class="btn btn-primary" data-auth-open="login">
                        Masuk
                    </button>
                    @endauth
                </div>

                <button type="button" class="tombol-menu-mobile" id="pembuka-menu-mobile" aria-label="Buka menu"
                    aria-expanded="false" aria-controls="menu-mobile">
                    @include('icon', ['name' => 'menu'])
                </button>
            </div>

            <div class="menu-mobile" id="menu-mobile">
                <a href="{{ route('home') }}" class="tautan-menu-mobile {{ request()->routeIs('home') ? 'is-active' : '' }}" @if (request()->routeIs('home')) aria-current="page" @endif>Beranda</a>
                <a href="{{ route('checker') }}" class="tautan-menu-mobile {{ request()->routeIs('checker') ? 'is-active' : '' }}" @if (request()->routeIs('checker')) aria-current="page" @endif>Deteksi Poster</a>
                <a href="{{ route('history') }}" class="tautan-menu-mobile {{ request()->routeIs('history') ? 'is-active' : '' }}" @if (request()->routeIs('history')) aria-current="page" @endif>Riwayat</a>
                <a href="{{ route('about') }}" class="tautan-menu-mobile {{ request()->routeIs('about') ? 'is-active' : '' }}" @if (request()->routeIs('about')) aria-current="page" @endif>Tentang</a>

                @if (request()->routeIs('home'))
                <button type="button" class="tautan-menu-mobile subscribe-mobile" data-subscribe-open>
                    Subscriptions
                </button>
                @endif

                @auth
                <a href="{{ route('profile') }}" class="btn btn-secondary btn-full">Profil</a>
                <form method="POST" action="{{ route('logout') }}" data-confirm-logout>
                    @csrf
                    <button type="submit" class="btn btn-primary btn-full">Keluar</button>
                </form>
                @else
                <button type="button" class="btn btn-primary btn-full" data-auth-open="login">Masuk</button>
                @endauth
            </div>
        </header>

        <main class="konten-utama">
            @if (session('status') && !request()->routeIs('profile'))
                <p role="status" aria-live="polite">{{ session('status') }}</p>
            @endif
            @yield('content')
        </main>

        {{-- Footer --}}
        <footer class="bagian-footer">
            <div class="container isi-footer">
                <div class="logo-website logo-footer"><span class="tanda-logo">@include('icon', ['name' =>
                        'spark'])</span>A.I Check</div>
                <p>© 2026 A.I Check</p>
                <span class="daya-footer"><span class="titik-daya" aria-hidden="true"></span>Model AI lokal</span>
            </div>
        </footer>
    </div>

    {{-- Modal masuk dan daftar. --}}
    <div class="modal-otorisasi" id="modal-otorisasi" aria-hidden="true"
        data-auth-initial-mode="{{ session('auth_mode', 'login') }}"
        data-auth-open-on-load="{{ session()->has('auth_mode') && $errors->any() ? 'true' : 'false' }}">
        <div class="panel-otorisasi" role="dialog" aria-modal="true" aria-labelledby="judul-modal">
            <div class="kepala-modal">
                <span class="ikon-modal" id="ikon-modal">@include('icon', ['name' => 'lock'])</span>
                <button type="button" class="tombol-tutup-modal" data-auth-close aria-label="Tutup">@include('icon',
                    ['name' => 'close'])</button>
            </div>

            <h2 class="judul-modal" id="judul-modal">Selamat datang kembali</h2>
            <p class="deskripsi-modal" id="deskripsi-modal">Masuk untuk menyimpan dan menyinkronkan riwayat analisis.
            </p>

            <form class="formulir-otorisasi" id="formulir-masuk" method="POST" action="{{ route('login') }}">
                @csrf
                @if (session()->has('auth_mode') && $errors->any() && session('auth_mode') === 'login')
                    <div class="alert-error" role="alert">{{ $errors->first() }}</div>
                @endif
                <label class="kolom-formulir">
                    <span class="label-formulir">Email</span>
                    <input class="form-control" type="email" name="email" value="{{ old('email') }}" required
                        autocomplete="email" placeholder="nama@email.com">
                </label>
                <label class="kolom-formulir">
                    <span class="label-formulir">Kata sandi</span>
                    <input class="form-control" type="password" name="password" required autocomplete="current-password"
                        placeholder="Minimal 8 karakter">
                </label>
                <div class="baris-formulir">
                    <label class="cek-formulir"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
                    <button type="button" class="tautan-formulir" style="border:0;background:none;">Lupa kata
                        sandi?</button>
                </div>
                <button class="btn btn-primary btn-full" type="submit">Masuk</button>
            </form>

            <form class="formulir-otorisasi" id="formulir-daftar" method="POST" action="{{ route('register') }}" hidden>
                @csrf
                @if (session()->has('auth_mode') && $errors->any() && session('auth_mode') === 'register')
                    <div class="alert-error" role="alert">{{ $errors->first() }}</div>
                @endif
                <label class="kolom-formulir"><span class="label-formulir">Nama lengkap</span><input
                        class="form-control" type="text" name="name" value="{{ old('name') }}" required minlength="3"
                        autocomplete="name"
                        placeholder="Masukkan nama lengkap"></label>
                <label class="kolom-formulir"><span class="label-formulir">Alamat email</span><input
                        class="form-control" type="email" name="email" value="{{ old('email') }}" required
                        autocomplete="email" placeholder="nama@email.com"></label>
                <label class="kolom-formulir"><span class="label-formulir">Kata sandi</span><input class="form-control"
                        type="password" name="password" required minlength="8" autocomplete="new-password"
                        placeholder="Minimal 8 karakter"></label>
                <label class="kolom-formulir"><span class="label-formulir">Konfirmasi kata sandi</span><input
                        class="form-control" type="password" name="password_confirmation" required minlength="8"
                        autocomplete="new-password"
                        placeholder="Ulangi kata sandi"></label>
                <label class="cek-formulir"><input type="checkbox" required> Saya menyetujui Syarat Penggunaan dan
                    Kebijakan Privasi A.I Check.</label>
                <button class="btn btn-primary btn-full" type="submit">Buat akun</button>
            </form>

            <div class="bagian-bawah-modal">
                <span id="teks-bawah-modal">Belum punya akun?</span>
                <button type="button" id="pengalih-mode-otorisasi" data-auth-mode="register">Daftar gratis</button>
            </div>
        </div>
    </div>

    {{-- Modal Subscribe --}}
    <div class="modal-subscribe" id="modal-subscribe" aria-hidden="true">
        <div class="panel-subscribe" role="dialog" aria-modal="true" aria-labelledby="judul-subscribe">

            <div class="subscribe-top">
                <div>
                    <span class="subscribe-eyebrow">A.I CHECK MEMBERSHIP</span>
                    <h2 id="judul-subscribe">Pilih Paket Berlangganan</h2>
                    <p class="subscribe-description">
                        Pilih paket yang sesuai dengan kebutuhanmu.
                    </p>
                </div>

                <button type="button" class="subscribe-close" data-subscribe-close aria-label="Tutup">
                    &times;
                </button>
            </div>

            <div class="subscribe-period" aria-label="Periode Premium">
                <button type="button" class="subscribe-period-button is-active" data-subscribe-period="month"
                    aria-pressed="true">
                    Bulanan
                </button>

                <button type="button" class="subscribe-period-button" data-subscribe-period="year" aria-pressed="false">
                    Tahunan <span>Hemat 28%</span>
                </button>
            </div>

            <div class="subscribe-plan-grid">

                {{-- Paket Free --}}
                <div class="subscribe-plan">
                    <span class="subscribe-plan-label">PAKET DASAR</span>

                    <h3>Free</h3>

                    <div class="subscribe-price">
                        <strong>Rp0</strong>
                        <span>/ selamanya</span>
                    </div>

                    <p class="subscribe-plan-description">
                        Mulai menggunakan A.I Check secara gratis.
                    </p>

                    <ul class="subscribe-list">
                        <li>Akses pemeriksaan AI terbatas</li>
                        <li>Maksimal 3 kali pemeriksaan per hari</li>
                        <li>Ukuran file maksimal 10 MB</li>
                        <li>Tanpa biaya langganan</li>
                    </ul>

                    <button type="button" class="btn btn-secondary subscribe-plan-button" data-auth-open="register"
                        data-subscribe-close>
                        Mulai Gratis
                    </button>
                </div>

                {{-- Paket Premium --}}
                <div class="subscribe-plan subscribe-plan-premium">
                    <span class="subscribe-plan-label">PAKET PREMIUM</span>

                    <h3>Premium</h3>

                    <div class="subscribe-price">
                        <strong id="subscribe-premium-price">Rp29.000</strong>
                        <span id="subscribe-premium-period">/bulan</span>
                    </div>

                    <p class="subscribe-plan-description">
                        Pilihan untuk pengguna yang membutuhkan fitur lebih.
                    </p>

                    <ul class="subscribe-list">
                        <li>Pemeriksaan tanpa batas harian</li>
                        <li>Unggah file berukuran lebih dari 10 MB</li>
                        <li>Akses fitur Premium tanpa kuota harian</li>
                        <li>Pilihan langganan bulanan atau tahunan</li>
                    </ul>

                    <button type="button" class="btn btn-primary subscribe-plan-button" id="subscribe-premium-select">
                        Pilih Premium
                    </button>
                </div>

            </div>

            <p class="subscribe-note">
                Paket Premium masih berupa pratinjau. Pembayaran dan aktivasi
                fitur Premium belum dihubungkan.
            </p>

        </div>
    </div>

</body>

</html>