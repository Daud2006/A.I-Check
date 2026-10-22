@extends('app')

@section('title', 'A.I Check | Tentang')

@section('content')
    {{-- Informasi tentang aplikasi. --}}
    <section class="about-page">
        <div class="page-container">
            <div class="about-panel">
                <span class="eyebrow about-eyebrow">@include('icon', ['name' => 'info', 'class' => 'icon-sm'])Tentang A.I Check</span>
                <h1 class="about-title">Prediksi visual, dengan privasi sebagai bagian dari proses.</h1>
                <p class="about-copy">A.I-Check membantu meninjau gambar menggunakan model klasifikasi yang berjalan secara lokal pada aplikasi. Hasilnya adalah prediksi model, bukan bukti pasti mengenai asal sebuah gambar.</p>
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
                        <p>Skor menunjukkan kelas yang dipilih model dan tidak menjamin kebenaran atau kepastian asal gambar.</p>
                    </article>
                </div>
            </div>
            <div class="about-note">
                <h2>Gunakan sebagai salah satu petunjuk</h2>
                <p>Model dapat salah mengklasifikasikan gambar, terutama pada jenis konten atau generator yang berbeda dari data pelatihannya. Pertimbangkan sumber, konteks, dan bukti lain sebelum mengambil keputusan.</p>
            </div>
        </div>
    </section>
@endsection
