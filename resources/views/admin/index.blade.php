@extends('layouts.app')

@section('title', 'A.I Check | Admin')

@section('content')
    {{-- Admin page kept separate from the public Figma screens and styled with the same tokens. --}}
    <section class="admin-page"><div class="container"><div class="admin-heading"><span class="eyebrow">Dashboard</span><h1 class="admin-title">Ringkasan sistem</h1></div><div class="admin-grid"><div class="admin-card"><div class="admin-card-label">Pengguna</div><div class="admin-card-value">1,248</div><div class="admin-card-note">Akun terdaftar</div></div><div class="admin-card"><div class="admin-card-label">Pemeriksaan</div><div class="admin-card-value">3,562</div><div class="admin-card-note">Total analisis</div></div><div class="admin-card"><div class="admin-card-label">AI terdeteksi</div><div class="admin-card-value">1,842</div><div class="admin-card-note">Skor di atas 75%</div></div></div></div></section>
@endsection
