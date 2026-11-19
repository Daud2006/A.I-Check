@extends('layouts.admin')

@section('title', 'A.I Check | Pengaturan Paket')

@section('content')
    <section class="admin-page" aria-labelledby="admin-plans-title">
        <div class="admin-page-heading admin-users-heading">
            <div>
                <span class="eyebrow">Administrasi</span>
                <h1 class="admin-title" id="admin-plans-title">Paket dan Kuota</h1>
                <p class="admin-description">Kelola paket tanpa menghapus riwayat subscription dan pembayaran.</p>
            </div>
            <a href="{{ route('admin.plans.create') }}" class="btn btn-primary">Tambah paket</a>
        </div>

        @if (session('status'))
            <div class="profile-notice" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="admin-analysis-validation" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="admin-data-panel admin-users-filter-panel" aria-label="Pencarian paket">
            <form method="GET" action="{{ route('admin.plans.index') }}" class="admin-analysis-filters">
                <label class="admin-filter-field admin-search-field">
                    <span class="admin-filter-label">Cari nama atau slug</span>
                    <input type="search" name="search" value="{{ $filters['search'] }}" class="admin-filter-control" maxlength="100">
                </label>
                <label class="admin-filter-field">
                    <span class="admin-filter-label">Status</span>
                    <select name="active" class="admin-filter-control">
                        <option value="">Semua paket</option>
                        <option value="1" @selected($filters['active'] === '1')>Aktif</option>
                        <option value="0" @selected($filters['active'] === '0')>Nonaktif</option>
                    </select>
                </label>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">Terapkan</button>
                    <a href="{{ route('admin.plans.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </section>

        <section class="admin-data-panel admin-users-table-panel" aria-label="Daftar paket">
            @if ($plans->isNotEmpty())
                <div class="admin-users-table-scroll">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Paket</th>
                                <th>Harga</th>
                                <th>Periode</th>
                                <th>Batas analisis</th>
                                <th>Subscription</th>
                                <th>Status</th>
                                <th><span class="admin-visually-hidden">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($plans as $plan)
                                <tr>
                                    <td>
                                        <span class="admin-table-primary">{{ $plan->name }}</span>
                                        <span class="admin-table-secondary">{{ $plan->slug }}</span>
                                    </td>
                                    <td>{{ $plan->currency }} {{ number_format((float) $plan->price, 2, ',', '.') }}</td>
                                    <td>{{ match ($plan->billing_interval) {'monthly' => 'Bulanan', 'yearly' => 'Tahunan', 'none' => 'Tidak berulang', default => $plan->billing_interval} }}</td>
                                    <td>{{ $plan->analysis_limit === null ? 'Tidak diatur' : number_format($plan->analysis_limit) }}</td>
                                    <td>{{ number_format($plan->subscriptions_count) }}</td>
                                    <td><span class="admin-badge {{ $plan->is_active ? 'is-premium' : '' }}">{{ $plan->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                    <td><a href="{{ route('admin.plans.show', $plan) }}" class="admin-user-detail-link">Detail</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($plans->hasPages())
                    <nav class="admin-pagination" aria-label="Navigasi halaman paket">
                        <span class="admin-pagination-summary">Menampilkan {{ $plans->firstItem() }}–{{ $plans->lastItem() }} dari {{ $plans->total() }}</span>
                        {{ $plans->links() }}
                    </nav>
                @endif
            @else
                <div class="admin-empty-state admin-users-empty">
                    <h2>{{ $filters['search'] !== '' || $filters['active'] !== '' ? 'Paket tidak ditemukan' : 'Belum ada paket' }}</h2>
                    <p>Paket yang dibuat akan muncul di halaman ini.</p>
                    <a href="{{ route('admin.plans.create') }}" class="btn btn-primary">Tambah paket</a>
                </div>
            @endif
        </section>
    </section>
@endsection
