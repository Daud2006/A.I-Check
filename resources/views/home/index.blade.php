@extends('layouts.app')

@section('title', 'A.I Check | Beranda')

@section('content')
    {{-- Home hero section: visual structure translated from the Figma Make screen. --}}
    <section class="home-hero">
        <div class="home-hero-grid"></div>
        <div class="home-hero-glow"></div>
        <div class="container home-hero-inner">
            <div>
                <span class="home-kicker"><span class="home-kicker-dot"></span>Teknologi deteksi generasi terbaru</span>
                <h1 class="home-title">Ketahui poster buatan <span class="home-title-accent">AI</span> dalam hitungan detik.</h1>
                <p class="home-copy">A.I Check membantu Anda memeriksa keaslian poster melalui analisis visual berbasis model Hugging Face yang cepat dan mudah dipahami.</p>
                <div class="home-actions">
                    <a href="{{ route('checker') }}" class="btn btn-light-blue">Periksa Poster Sekarang <x-icon name="arrow" /></a>
                    <a href="#cara-kerja" class="btn btn-outline">Lihat cara kerja</a>
                </div>
                <div class="home-benefits">
                    <span class="home-benefit"><x-icon name="check" />Tanpa instalasi</span>
                    <span class="home-benefit"><x-icon name="check" />Privasi terjaga</span>
                    <span class="home-benefit"><x-icon name="check" />Hasil instan</span>
                </div>
            </div>

            <div class="scan-preview-wrap">
                <div class="scan-preview-back"></div>
                <div class="scan-preview">
                    <div class="scan-window-top">
                        <div class="scan-lights"><i class="scan-light red"></i><i class="scan-light yellow"></i><i class="scan-light blue"></i></div>
                        <span class="scan-window-label">AI scan</span>
                    </div>
                    <div class="scan-poster-shell">
                        <div class="scan-poster">
                            <div class="scan-poster-shade"></div>
                            <div class="scan-poster-copy"><strong>FUTURE<br>NATURE</strong><span>Design conference 2025</span></div>
                            <div class="scan-line"></div>
                        </div>
                        <div class="scan-score">
                            <div><div class="scan-score-label">Probabilitas buatan AI</div><div class="scan-score-value">87.4%</div></div>
                            <div class="scan-score-ring"><span class="scan-score-ring-inner">AI</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Workflow section: three cards from the Figma design. --}}
    <section class="home-workflow" id="cara-kerja">
        <div class="container">
            <div class="section-heading">
                <span class="eyebrow">Cara kerja</span>
                <h2 class="section-title">Tiga langkah, hasil yang jelas</h2>
                <p class="section-copy">Tidak perlu keahlian teknis untuk mulai memverifikasi konten visual.</p>
            </div>
            <div class="workflow-grid">
                <article class="workflow-card"><div class="workflow-card-top"><span class="workflow-icon"><x-icon name="upload" /></span><span class="workflow-number">01</span></div><h3 class="workflow-title">Unggah poster</h3><p class="workflow-copy">Pilih gambar JPG, PNG, atau WEBP dari perangkat Anda.</p></article>
                <article class="workflow-card"><div class="workflow-card-top"><span class="workflow-icon"><x-icon name="spark" /></span><span class="workflow-number">02</span></div><h3 class="workflow-title">Analisis AI</h3><p class="workflow-copy">Model memeriksa pola piksel dan karakteristik visual secara otomatis.</p></article>
                <article class="workflow-card"><div class="workflow-card-top"><span class="workflow-icon"><x-icon name="shield" /></span><span class="workflow-number">03</span></div><h3 class="workflow-title">Dapatkan hasil</h3><p class="workflow-copy">Lihat persentase dan indikator visual yang mudah dipahami.</p></article>
            </div>
        </div>
    </section>

    {{-- Statistics strip from the Figma design. --}}
    <section class="home-stats">
        <div class="container home-stats-grid">
            <div><p class="home-stat-value">10 MB</p><p class="home-stat-label">Ukuran maksimal</p></div>
            <div><p class="home-stat-value">3 format</p><p class="home-stat-label">JPG, PNG &amp; WEBP</p></div>
            <div><p class="home-stat-value">± 5 detik</p><p class="home-stat-label">Waktu analisis</p></div>
        </div>
    </section>
@endsection
