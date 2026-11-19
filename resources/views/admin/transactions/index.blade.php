@extends('layouts.admin')

@section('title', 'A.I Check | Manajemen Transaksi')

@section('content')
    <section class="admin-page admin-page-wide" aria-labelledby="admin-transactions-title">
        <div class="admin-page-heading admin-users-heading">
            <div>

                <h1 class="admin-title" id="admin-transactions-title">Manajemen Transaksi</h1>

            </div>
        </div>

        @if ($errors->any())
            <div class="admin-analysis-validation" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="admin-data-panel admin-users-filter-panel" aria-label="Pencarian dan filter transaksi">
            <form method="GET" action="{{ route('admin.transactions.index') }}" class="admin-analysis-filters">
                <label class="admin-filter-field admin-search-field">
                    <span class="admin-filter-label">Cari pengguna atau ID transaksi</span>
                    <input type="search" name="search" value="{{ $filters['search'] }}" class="admin-filter-control" maxlength="100">
                </label>
                <label class="admin-filter-field">
                    <span class="admin-filter-label">Status pembayaran</span>
                    <select name="status" class="admin-filter-control">
                        <option value="">Semua status</option>
                        @foreach (\App\Models\Payment::STATUSES as $status)
                            <option value="{{ $status }}" @selected($filters['status'] === $status)>
                                {{ match ($status) {
                                    'pending' => 'Menunggu',
                                    'paid' => 'Berhasil',
                                    'failed' => 'Gagal',
                                    'refunded' => 'Dikembalikan',
                                    default => ucfirst($status),
                                } }}
                            </option>
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
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">
                        @include('icon', ['name' => 'search', 'class' => 'icon-sm'])
                        Terapkan
                    </button>
                    @if (collect($filters)->contains(fn (string $value): bool => $value !== ''))
                        <a href="{{ route('admin.transactions.index') }}" class="btn btn-admin-reset">Reset filter</a>
                    @endif
                </div>
            </form>
        </section>

        <section class="admin-data-panel admin-users-table-panel" aria-label="Daftar transaksi">
            @if ($payments->isNotEmpty())
                <div class="admin-users-table-scroll">
                    <table class="admin-table admin-transactions-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pengguna</th>
                                <th>Paket</th>
                                <th class="admin-numeric-cell">Jumlah</th>
                                <th>Status</th>
                                <th>Dibuat</th>
                                <th><span class="admin-visually-hidden">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($payments as $payment)
                                <tr>
                                    <td>#{{ $payment->id }}</td>
                                    <td>
                                        <span class="admin-table-primary">{{ $payment->user?->name ?? 'Pengguna tidak tersedia' }}</span>
                                        <span class="admin-table-secondary">{{ $payment->user?->email ?? '' }}</span>
                                    </td>
                                    <td>{{ $payment->subscription?->planName() ?? 'Paket tidak tersedia' }}</td>
                                    <td class="admin-numeric-cell">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2, ',', '.') }}</td>
                                    <td>
                                        <span class="admin-badge {{ $payment->status === 'paid' ? 'is-premium' : '' }}">
                                            {{ match ($payment->status) {
                                                'pending' => 'Menunggu',
                                                'paid' => 'Berhasil',
                                                'failed' => 'Gagal',
                                                'refunded' => 'Dikembalikan',
                                                default => 'Tidak diketahui',
                                            } }}
                                        </span>
                                        @if ($payment->environment === 'sandbox')
                                            <span class="admin-table-secondary">Sandbox</span>
                                        @endif
                                    </td>
                                    <td>{{ $payment->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td>
                                        <a href="{{ route('admin.transactions.show', $payment->id) }}" class="admin-user-detail-link">
                                            Detail @include('icon', ['name' => 'arrow', 'class' => 'icon-sm'])
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($payments->hasPages())
                    <nav class="admin-pagination" aria-label="Navigasi halaman transaksi">
                        <span class="admin-pagination-summary">
                            Menampilkan {{ $payments->firstItem() }}–{{ $payments->lastItem() }} dari {{ $payments->total() }}
                        </span>
                        {{ $payments->links() }}
                    </nav>
                @endif
            @else
                <div class="admin-empty-state admin-users-empty">
                    <span class="admin-empty-icon">@include('icon', ['name' => 'check'])</span>
                    <h2>{{ collect($filters)->contains(fn (string $value): bool => $value !== '') ? 'Transaksi tidak ditemukan' : 'Belum ada transaksi' }}</h2>
                    <p>Transaksi yang tercatat akan muncul di halaman ini.</p>
                </div>
            @endif
        </section>
    </section>
@endsection
