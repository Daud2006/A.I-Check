@extends('app')

@section('title', 'A.I Check | Autentikasi')

@section('content')
    {{-- Halaman masuk dan daftar. --}}
    <section class="profile-page">
        <div class="container">
            <div class="profile-card">
                <span class="eyebrow">@include('icon', ['name' => 'lock', 'class' => 'icon-sm'])Akses aman</span>
                <h1 class="profile-title auth-page-title">Masuk ke A.I-Check</h1>
                <p class="profile-copy">Masuk untuk menyimpan hasil analisis ke akun dan mengakses riwayat Anda.</p>
                <button type="button" class="btn btn-primary auth-page-action" data-auth-open="login">Masuk ke akun</button>
                <p class="auth-register-prompt">Belum memiliki akun? <button type="button" class="auth-text-button" data-auth-open="register">Buat akun</button></p>
            </div>
        </div>
    </section>
@endsection
