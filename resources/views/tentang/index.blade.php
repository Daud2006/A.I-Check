@extends('app')

@section('title', 'A.I Check | Tentang')

@section('content')
    {{-- Informasi tentang aplikasi. --}}
    <section class="about-page">
        <div class="page-container">
            <div class="about-panel">
                <h1 class="about-title">Prediksi visual, dengan privasi sebagai bagian dari proses.</h1>
                <div class="about-features">
                    <article class="about-feature">
                        @include('icon', ['name' => 'layers'])
                        <h2>Model lokal</h2>
                        <p>Analisis menggunakan model yang dikelola bersama layanan aplikasi, bukan API inferensi berbayar.</p>
                    </article>
                    <article class="about-feature">
                        @include('icon', ['name' => 'shield'])
                        <h2>Penyimpanan privat</h2>
                        <p>Gambar analisis disimpan pada storage privat dan aksesnya dibatasi kepada pemilik akun.</p>
                    </article>
                    <article class="about-feature">
                        @include('icon', ['name' => 'info'])
                        <h2>Hasil perlu ditinjau</h2>
                        <p>Probabilitas AI merupakan estimasi model yang ditampilkan terpisah dari kategori hasil, bukan jaminan kebenaran atau kepastian asal gambar.</p>
                    </article>
                </div>
            </div>
        </div>
    </section>
@endsection
