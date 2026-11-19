@extends('app')

@section('title', 'A.I Check | Tentang')

@section('content')
    {{-- Informasi tentang aplikasi. --}}
    <section class="about-page">
        <div class="page-container">
            <div class="about-panel">
                <h1 class="about-title">Tentang A.I-Check</h1>
                <p>Proyek kuliah oleh Daud Al Khilal dan Muhammad Bakeri.</p>
                <div class="about-features">
                    <article class="about-feature">
                        @include('icon', ['name' => 'layers'])
                        <h2>Model lokal</h2>
                        <p>Model lokal memperkirakan apakah gambar dibuat atau diedit dengan AI.</p>
                    </article>
                    <article class="about-feature">
                        @include('icon', ['name' => 'shield'])
                        <h2>Penyimpanan privat</h2>
                        <p>Gambar disimpan secara privat dan bisa dibuka melalui akun pemiliknya.</p>
                    </article>
                    <article class="about-feature">
                        @include('icon', ['name' => 'info'])
                        <h2>Hasil perlu ditinjau</h2>
                        <p>Gunakan hasil sebagai petunjuk awal saat memeriksa asal gambar.</p>
                    </article>
                </div>
            </div>
            <section class="about-note faq" aria-labelledby="judul-faq">
                <h2 id="judul-faq">Panduan &amp; FAQ</h2>
                <details>
                    <summary>Apa arti kategori hasil?</summary>
                    <dl>
                        <dt>AI-GENERATED</dt><dd>Gambar kemungkinan dibuat dengan AI.</dd>
                        <dt>AI-EDITED</dt><dd>Gambar kemungkinan diedit dengan bantuan AI.</dd>
                        <dt>REAL</dt><dd>Gambar cenderung berasal dari manusia menurut model.</dd>
                        <dt>UNCERTAIN</dt><dd>Model belum cukup yakin untuk menentukan kategori gambar.</dd>
                    </dl>
                </details>
                <details>
                    <summary>Bagaimana membaca Probabilitas AI?</summary>
                    <p>Angka ini menunjukkan seberapa kuat model mendeteksi indikasi AI pada gambar. Untuk keputusan penting, periksa juga sumber dan konteks gambar.</p>
                </details>
                <details>
                    <summary>Bagaimana kuota dihitung?</summary>
                    <p>Free mendapat {{ \App\Services\AnalysisQuotaService::FREE_WEEKLY_LIMIT }} analisis. Kuota Premium mengikuti <a class="tautan-dokumen" href="{{ route('plans.index') }}">paket yang dipilih</a>. Sistem melihat penggunaan dalam 7 hari terakhir. Setiap penggunaan keluar dari hitungan setelah lewat 7 hari.</p>
                </details>
                <details>
                    <summary>Bagaimana cara menghapus gambar?</summary>
                    <p>Buka <a class="tautan-dokumen" href="{{ route('history') }}">Riwayat</a> dan hapus analisis yang dipilih. Aplikasi mencoba membersihkan file gambar ketika file tersebut sudah tidak digunakan oleh analisis lain.</p>
                </details>
            </section>
        </div>
    </section>
@endsection
