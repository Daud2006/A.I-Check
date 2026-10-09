@extends('app')

@section('title', 'A.I Check | Autentikasi')

@section('content')
    {{-- Halaman masuk dan daftar. --}}
    <section class="profile-page">
        <div class="container">
            <div class="profile-card">
                <span class="eyebrow">@include('icon', ['name' => 'lock', 'class' => 'icon-sm'])Akses aman</span>
                <h1 class="profile-title" style="margin-top:16px">Masuk ke A.I Check</h1>
                <p class="profile-copy">Gunakan tombol Masuk pada navigasi untuk membuka modal autentikasi yang sesuai desain.</p>
                <button type="button" class="btn btn-primary" style="margin-top:24px" data-auth-open="login">Buka Login</button>
            </div>
        </div>
    </section>
@endsection
