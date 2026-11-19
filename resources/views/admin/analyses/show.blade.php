@extends('layouts.admin')

@section('title', 'A.I Check | Detail Analisis')

@section('content')
    @php
        $score = $analysis->confidence_score === null
            ? null
            : (float) $analysis->confidence_score;
        $isAiProbability = $analysis->model_name === 'simple_ai_image_detector'
            && $score !== null
            && $score >= 0
            && $score <= 1;
    @endphp

    <section class="admin-page" aria-labelledby="admin-analysis-detail-title">
        <div class="admin-user-detail-top">
            <a href="{{ route('admin.analyses.index') }}" class="admin-back-link">
                @include('icon', ['name' => 'arrow', 'class' => 'icon-sm'])
                Kembali ke analisis
            </a>
            <span class="admin-badge {{ $analysis->status === 'completed' ? 'is-premium' : '' }}">
                {{ match ($analysis->status) {
                    'pending' => 'Menunggu',
                    'processing' => 'Diproses',
                    'completed' => 'Selesai',
                    'failed' => 'Gagal',
                    default => 'Tidak diketahui',
                } }}
            </span>
        </div>

        <div class="admin-user-profile-heading">
            <span class="admin-user-profile-avatar" aria-hidden="true">@include('icon', ['name' => 'image'])</span>
            <div>
                <span class="eyebrow">@include('icon', ['name' => 'image', 'class' => 'icon-sm'])Detail pemeriksaan</span>
                <h1 class="admin-title" id="admin-analysis-detail-title">Analisis #{{ $analysis->id }}</h1>
                <p class="admin-description">
                    {{ $analysis->created_at?->format('d/m/Y H:i:s') ?? 'Waktu tidak tersedia' }}
                </p>
            </div>
        </div>

        <div class="admin-user-detail-grid">
            <section class="admin-data-panel" aria-labelledby="admin-analysis-result-title">
                <div class="admin-section-heading">
                    <div>
                        <h2 class="admin-section-title" id="admin-analysis-result-title">Hasil analisis</h2>
                        <p class="admin-section-description">Nilai yang tersimpan dari proses deteksi.</p>
                    </div>
                </div>
                <dl class="admin-user-info-list">
                    <div>
                        <dt>Status pemrosesan</dt>
                        <dd>{{ $analysis->status }}</dd>
                    </div>
                    <div>
                        <dt>Label klasifikasi</dt>
                        <dd>{{ $analysis->result_label ?? 'Tidak tersedia' }}</dd>
                    </div>
                    @if ($isAiProbability)
                        <div>
                            <dt>Probabilitas AI (p_ai)</dt>
                            <dd>{{ number_format($score * 100, 2, ',', '.') }}%</dd>
                        </div>
                    @elseif ($score !== null)
                        <div>
                            <dt>Skor tersimpan (confidence_score)</dt>
                            <dd>{{ number_format($score, 6, '.', '') }}</dd>
                        </div>
                    @else
                        <div>
                            <dt>Skor</dt>
                            <dd>Tidak tersedia</dd>
                        </div>
                    @endif
                    <div>
                        <dt>Model / metode</dt>
                        <dd>{{ $analysis->model_name ?? 'Tidak tersedia' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="admin-data-panel" aria-labelledby="admin-analysis-user-title">
                <div class="admin-section-heading">
                    <div>
                        <h2 class="admin-section-title" id="admin-analysis-user-title">Informasi pengguna</h2>
                        <p class="admin-section-description">Pemilik record analisis ini.</p>
                    </div>
                </div>
                <dl class="admin-user-info-list">
                    <div>
                        <dt>Nama</dt>
                        <dd>{{ $analysis->user?->name ?? 'Pengguna tidak tersedia' }}</dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd>{{ $analysis->user?->email ?? 'Tidak tersedia' }}</dd>
                    </div>
                    <div>
                        <dt>Tipe file</dt>
                        <dd>{{ $analysis->mime_type }}</dd>
                    </div>
                    <div>
                        <dt>Ukuran file</dt>
                        <dd>{{ number_format($analysis->file_size / 1024, 1, ',', '.') }} KB</dd>
                    </div>
                    <div>
                        <dt>Terakhir diperbarui</dt>
                        <dd>{{ $analysis->updated_at?->format('d/m/Y H:i:s') ?? 'Tidak tersedia' }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="admin-data-panel admin-analysis-visualization" aria-labelledby="admin-analysis-visualization-title">
            <div class="admin-section-heading">
                <div>
                    <h2 class="admin-section-title" id="admin-analysis-visualization-title">Visualisasi lokasi elemen AI</h2>
                    <p class="admin-section-description">Heatmap, mask, bounding box, dan data visualisasi tidak disimpan pada record analisis saat ini.</p>
                </div>
            </div>
            <div class="admin-analysis-visualization-empty">
                @include('icon', ['name' => 'image'])
                <p>
                    Visualisasi lokasi elemen AI belum tersedia pada hasil analisis ini.
                    Pengembangan berikutnya memerlukan tipe visualisasi beserta referensi berkas privat,
                    atau geometri mask/koordinat yang terikat pada dimensi gambar.
                </p>
            </div>
        </section>
    </section>
@endsection
