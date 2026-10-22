@extends('app')

@section('title', 'A.I Check | Beranda')

@section('content')
    {{-- Bagian utama beranda. --}}
    <section class="home-hero">
        <div class="home-hero-grid"></div>
        <div class="home-hero-glow"></div>
        <div class="container home-hero-inner">
            <div>
                <span class="home-kicker"><span class="home-kicker-dot" aria-hidden="true"></span>Analisis gambar secara lokal</span>
                <h1 class="home-title">Pahami asal visual poster dengan <span class="home-title-accent">A.I-Check</span>.</h1>
                <p class="home-copy">Unggah poster untuk mendapatkan prediksi kelas dari model AI lokal. Hasil membantu peninjauan, bukan bukti pasti tentang asal gambar.</p>
                <div class="home-actions">
                    <a href="{{ route('checker') }}" class="btn btn-light-blue">Mulai pemeriksaan @include('icon', ['name' => 'arrow'])</a>
                    <a href="#cara-kerja" class="btn btn-outline">Lihat cara kerja</a>
                </div>
                <div class="home-benefits" aria-label="Informasi layanan">
                    <span class="home-benefit">@include('icon', ['name' => 'shield'])Gambar tersimpan privat</span>
                    <span class="home-benefit">@include('icon', ['name' => 'layers'])Model berjalan lokal</span>
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
                        <div class="scan-score" aria-label="Ilustrasi antarmuka, bukan hasil prediksi">
                            <div>
                                <div class="scan-score-label">Contoh tampilan hasil</div>
                                <div class="scan-score-value">Prediksi model</div>
                                <p class="scan-score-note">Nilai ditampilkan setelah gambar dianalisis.</p>
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
                <span class="eyebrow">Cara kerja</span>
                <h2 class="section-title">Pemeriksaan yang sederhana dan transparan</h2>
                <p class="section-copy">Anda tetap memegang kendali atas gambar dan dapat meninjau kembali analisis dari akun Anda.</p>
            </div>
            <div class="workflow-grid">
                <article class="workflow-card"><div class="workflow-card-top"><span class="workflow-icon">@include('icon', ['name' => 'upload'])</span><span class="workflow-number">01</span></div><h3 class="workflow-title">Pilih gambar</h3><p class="workflow-copy">Unggah gambar JPEG, PNG, atau WebP hingga 10 MB.</p></article>
                <article class="workflow-card"><div class="workflow-card-top"><span class="workflow-icon">@include('icon', ['name' => 'spark'])</span><span class="workflow-number">02</span></div><h3 class="workflow-title">Jalankan analisis</h3><p class="workflow-copy">Model lokal memproses gambar yang disimpan pada penyimpanan privat aplikasi.</p></article>
                <article class="workflow-card"><div class="workflow-card-top"><span class="workflow-icon">@include('icon', ['name' => 'shield'])</span><span class="workflow-number">03</span></div><h3 class="workflow-title">Tinjau prediksi</h3><p class="workflow-copy">Lihat kelas yang diprediksi dan skor kelas tersebut. Model tetap dapat keliru.</p></article>
            </div>
        </div>
    </section>

    <section class="home-stats" aria-label="Batas dan privasi pemeriksaan">
        <div class="container home-stats-grid">
            <div><p class="home-stat-value">10 MB</p><p class="home-stat-label">Ukuran unggahan maksimal</p></div>
            <div><p class="home-stat-value">JPEG · PNG · WebP</p><p class="home-stat-label">Format yang didukung</p></div>
            <div><p class="home-stat-value">Lokal</p><p class="home-stat-label">Pemrosesan model</p></div>
        </div>
    </section>
@endsection
