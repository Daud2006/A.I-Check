@extends('layouts.admin')

@section('title', 'A.I Check | Manajemen Analisis')

@section('content')
    <section class="admin-page" aria-labelledby="admin-analyses-title">
        <div class="admin-page-heading admin-users-heading">
            <div>
                <span class="eyebrow">@include('icon', ['name' => 'image', 'class' => 'icon-sm'])Administrasi</span>
                <h1 class="admin-title" id="admin-analyses-title">Manajemen Analisis</h1>
                <p class="admin-description">Cari dan tinjau hasil pemeriksaan gambar yang tersimpan.</p>
            </div>
            <div class="admin-users-total">
                <strong>{{ number_format($analyses->total()) }}</strong>
                <span>analisis ditemukan</span>
            </div>
        </div>

        @if ($errors->any())
            <div class="admin-analysis-validation" role="alert">
                <strong>Filter tidak dapat diterapkan.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="admin-data-panel admin-users-filter-panel" aria-label="Pencarian dan filter analisis">
            <form method="GET" action="{{ route('admin.analyses.index') }}" class="admin-analysis-filters">
                <label class="admin-filter-field admin-search-field">
                    <span class="admin-filter-label">Cari pengguna</span>
                    <input
                        type="search"
                        name="search"
                        value="{{ $filters['search'] }}"
                        class="admin-filter-control"
                        placeholder="Nama atau email"
                        maxlength="100"
                    >
                </label>

                <label class="admin-filter-field">
                    <span class="admin-filter-label">Status</span>
                    <select name="status" class="admin-filter-control">
                        <option value="">Semua status</option>
                        @foreach (\App\Models\ImageAnalysis::STATUSES as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>
                                {{ match ($status) {
                                    'pending' => 'Menunggu',
                                    'processing' => 'Diproses',
                                    'completed' => 'Selesai',
                                    'failed' => 'Gagal',
                                    default => ucfirst($status),
                                } }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="admin-filter-field">
                    <span class="admin-filter-label">Hasil klasifikasi</span>
                    <select name="result_label" class="admin-filter-control">
                        <option value="">Semua hasil</option>
                        @foreach ($resultLabels as $label)
                            <option value="{{ $label }}" @selected($filters['result_label'] === $label)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="admin-filter-field">
                    <span class="admin-filter-label">Dari tanggal</span>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="admin-filter-control">
                </label>

                <label class="admin-filter-field">
                    <span class="admin-filter-label">Sampai tanggal</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="admin-filter-control">
                </label>

                <label class="admin-filter-field">
                    <span class="admin-filter-label">Tipe file</span>
                    <select name="mime_type" class="admin-filter-control">
                        <option value="">Semua tipe</option>
                        @foreach ($mimeTypes as $mimeType)
                            <option value="{{ $mimeType }}" @selected($filters['mime_type'] === $mimeType)>{{ $mimeType }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">
                        @include('icon', ['name' => 'search', 'class' => 'icon-sm'])
                        Terapkan
                    </button>
                    @if (collect($filters)->contains(fn (string $value): bool => $value !== ''))
                        <a href="{{ route('admin.analyses.index') }}" class="btn btn-secondary">Reset filter</a>
                    @endif
                </div>
            </form>
        </section>

        <section class="admin-data-panel admin-users-table-panel" aria-label="Daftar analisis">
            @if ($analyses->isNotEmpty())
                <div class="admin-users-table-scroll">
                    <table class="admin-table admin-analyses-table">
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Pengguna</th>
                                <th scope="col">Waktu</th>
                                <th scope="col">Tipe file</th>
                                <th scope="col">Status</th>
                                <th scope="col">Hasil / nilai tersimpan</th>
                                <th scope="col"><span class="admin-visually-hidden">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($analyses as $analysis)
                                @php
                                    $score = $analysis->confidence_score === null
                                        ? null
                                        : (float) $analysis->confidence_score;
                                    $isAiProbability = $analysis->model_name === 'simple_ai_image_detector'
                                        && $score !== null
                                        && $score >= 0
                                        && $score <= 1;
                                @endphp
                                <tr>
                                    <td>#{{ $analysis->id }}</td>
                                    <td>
                                        <span class="admin-table-primary">{{ $analysis->user?->name ?? 'Pengguna tidak tersedia' }}</span>
                                        @if ($analysis->user?->email)
                                            <span class="admin-table-secondary">{{ $analysis->user->email }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $analysis->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td>{{ $analysis->mime_type }}</td>
                                    <td>
                                        <span class="admin-badge {{ $analysis->status === 'completed' ? 'is-premium' : '' }}">
                                            {{ match ($analysis->status) {
                                                'pending' => 'Menunggu',
                                                'processing' => 'Diproses',
                                                'completed' => 'Selesai',
                                                'failed' => 'Gagal',
                                                default => 'Tidak diketahui',
                                            } }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($analysis->result_label)
                                            <span class="admin-table-primary">{{ $analysis->result_label }}</span>
                                        @else
                                            <span class="admin-table-secondary">Hasil belum tersedia</span>
                                        @endif
                                        @if ($isAiProbability)
                                            <span class="admin-table-secondary">Probabilitas AI: {{ number_format($score * 100, 2, ',', '.') }}%</span>
                                        @elseif ($score !== null)
                                            <span class="admin-table-secondary">confidence_score: {{ number_format($score, 6, '.', '') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a
                                            href="{{ route('admin.analyses.show', $analysis->id) }}"
                                            class="admin-user-detail-link"
                                            aria-label="Lihat detail analisis {{ $analysis->id }}"
                                        >
                                            Lihat detail
                                            @include('icon', ['name' => 'arrow', 'class' => 'icon-sm'])
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($analyses->hasPages())
                    <nav class="admin-pagination" aria-label="Navigasi halaman analisis">
                        <span class="admin-pagination-summary">
                            Menampilkan {{ $analyses->firstItem() }}–{{ $analyses->lastItem() }} dari {{ $analyses->total() }}
                        </span>
                        {{ $analyses->links() }}
                    </nav>
                @endif
            @else
                <div class="admin-empty-state admin-users-empty">
                    <span class="admin-empty-icon">@include('icon', ['name' => 'image'])</span>
                    <h2>{{ collect($filters)->contains(fn (string $value): bool => $value !== '') ? 'Analisis tidak ditemukan' : 'Belum ada analisis' }}</h2>
                    <p>
                        {{ collect($filters)->contains(fn (string $value): bool => $value !== '')
                            ? 'Coba ubah pencarian atau filter untuk melihat hasil lain.'
                            : 'Analisis gambar yang tersimpan akan muncul di halaman ini.' }}
                    </p>
                    @if (collect($filters)->contains(fn (string $value): bool => $value !== ''))
                        <a href="{{ route('admin.analyses.index') }}" class="btn btn-secondary">Reset pencarian dan filter</a>
                    @endif
                </div>
            @endif
        </section>
    </section>
@endsection
