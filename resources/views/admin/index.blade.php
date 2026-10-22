@extends('app')

@section('title', 'A.I Check | Admin')

@section('content')
    <section class="admin-page">
        <div class="page-container">
            <div class="admin-panel">
                <span class="eyebrow">@include('icon', ['name' => 'lock', 'class' => 'icon-sm'])Area administrator</span>
                <h1 class="admin-title">Panel admin</h1>
                <p class="admin-description">Halaman ini hanya tersedia untuk akun administrator. Fitur pengelolaan belum tersedia pada versi saat ini.</p>
                <div class="admin-status" role="status">
                    @include('icon', ['name' => 'info', 'class' => 'icon-sm'])
                    Belum ada alat administrasi yang aktif.
                </div>
            </div>
        </div>
    </section>
@endsection
