@extends('app')

@section('title', 'A.I Check | Beranda')

@section('content')
    {{-- Bagian utama beranda. --}}
    <section class="home-hero">
        <div class="home-hero-grid"></div>
        <div class="home-hero-glow"></div>
        <div class="container home-hero-inner">
            <div>
                <h1 class="home-title">Apakah gambar ini <span class="home-title-accent">dibuat dengan AI?</span></h1>
                <p class="home-copy">Unggah gambar untuk melihat hasil deteksi dan Probabilitas AI.</p>
                <div class="home-actions">
                    <a href="{{ route('checker') }}" class="btn btn-light-blue">Mulai Deteksi Gambar @include('icon', ['name' => 'arrow'])</a>
                </div>
            </div>

            <div class="scan-preview-wrap">
                <div class="scan-preview-back"></div>
                <div class="scan-preview">
                    <div class="scan-window-top">
                        <div class="scan-lights"><i class="scan-light red"></i><i class="scan-light yellow"></i><i class="scan-light blue"></i></div>
                        <span class="scan-window-label">Contoh Hasil Deteksi</span>
                    </div>
                    <div class="scan-poster-shell">
                        <div class="scan-poster">
                            <div class="scan-poster-shade"></div>
                            <div class="scan-poster-copy" aria-hidden="true"><strong>FUTURE<br>NATURE</strong></div>
                            <div class="scan-line"></div>
                        </div>
                        <div class="scan-score">
                            <div>
                                <div class="scan-score-label">Probabilitas AI</div>
                                <div class="scan-score-value">86% · AI-GENERATED</div>
                            </div>
                            <div class="scan-score-ring" aria-hidden="true"><span class="scan-score-ring-inner">@include('icon', ['name' => 'spark'])</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-workflow" id="cara-kerja">
        <div class="container">
            <div class="section-heading">
                <h2 class="section-title">Cara kerja</h2>
            </div>
            <div class="workflow-grid">
                <article class="workflow-card"><div class="workflow-card-top"><span class="workflow-icon">@include('icon', ['name' => 'upload'])</span></div><h3 class="workflow-title">Pilih gambar</h3><p class="workflow-copy">Unggah gambar JPEG, PNG, atau WebP hingga 10 MB.</p></article>
                <article class="workflow-card"><div class="workflow-card-top"><span class="workflow-icon">@include('icon', ['name' => 'spark'])</span></div><h3 class="workflow-title">Jalankan analisis</h3><p class="workflow-copy">Tunggu model lokal memeriksa gambar.</p></article>
                <article class="workflow-card"><div class="workflow-card-top"><span class="workflow-icon">@include('icon', ['name' => 'shield'])</span></div><h3 class="workflow-title">Tinjau hasil</h3><p class="workflow-copy">Lihat kategori dan Probabilitas AI.</p></article>
            </div>
        </div>
    </section>
@endsection
