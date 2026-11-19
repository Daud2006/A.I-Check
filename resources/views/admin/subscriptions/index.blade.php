@extends('layouts.admin')

@section('title', 'Langganan Admin | A.I-Check')

@section('content')
    <section class="admin-page admin-page-wide" aria-labelledby="judul-langganan-admin">
        <div class="admin-page-heading">
            <h1 class="admin-title" id="judul-langganan-admin">Langganan</h1>
        </div>
        @if (session('status'))
            <p class="profile-notice" role="status">{{ session('status') }}</p>
        @endif
        <section class="admin-data-panel admin-users-filter-panel">
            <form action="{{ route('admin.subscriptions.index') }}" method="GET" class="admin-analysis-filters">
                <label class="admin-filter-field admin-search-field">
                    <span class="admin-filter-label">Nama atau email</span>
                    <input name="search" type="search" value="{{ $search }}" class="admin-filter-control" maxlength="100">
                </label>
                <label class="admin-filter-field">
                    <span class="admin-filter-label">Sumber</span>
                    <select name="source" class="admin-filter-control">
                        <option value="">Semua sumber</option>
                        <option value="payment" @selected($source === 'payment')>Pembayaran</option>
                        <option value="gift" @selected($source === 'gift')>Hadiah</option>
                    </select>
                </label>
                <div class="admin-filter-actions">
                    <a href="{{ route('admin.plans.gift.create') }}" class="btn btn-secondary">Gift Paket</a>
                    <button type="submit" class="btn btn-primary">@include('icon', ['name' => 'search', 'class' => 'icon-sm'])Terapkan</button>
                </div>
            </form>
        </section>
        <section class="admin-data-panel admin-users-table-panel" aria-label="Daftar langganan">
            <div class="admin-users-table-scroll">
                <table class="admin-table">
                    <thead><tr><th>Pengguna</th><th>Paket</th><th>Status</th><th>Mulai</th><th>Berakhir</th><th>Sumber</th></tr></thead>
                    <tbody>
                        @forelse ($subscriptions as $subscription)
                            @php
                                $status = match (true) {
                                    $subscription->status === 'cancelled' => 'Dibatalkan',
                                    $subscription->status === 'expired' || $subscription->ends_at?->isPast() => 'Kedaluwarsa',
                                    $subscription->status === 'pending' => 'Menunggu pembayaran',
                                    $subscription->starts_at?->isFuture() => 'Terjadwal',
                                    $subscription->granted_by || $subscription->payments->contains('status', 'paid') => 'Aktif',
                                    default => 'Belum terverifikasi',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <a class="admin-table-primary" href="{{ route('admin.users.show', $subscription->user_id) }}">{{ $subscription->user->name }}</a>
                                    <span class="admin-table-secondary">{{ $subscription->user->email }}</span>
                                </td>
                                <td>{{ $subscription->planName() ?? '—' }}</td>
                                <td><span class="admin-badge {{ $status === 'Aktif' ? 'is-premium' : '' }}">{{ $status }}</span></td>
                                <td>{{ $subscription->starts_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>{{ $subscription->ends_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td>
                                    @if ($subscription->granted_by)
                                        Hadiah · Admin #{{ $subscription->granted_by }}
                                    @elseif ($payment = $subscription->payments->sortByDesc('id')->first())
                                        <a href="{{ route('admin.transactions.show', $payment->id) }}">Transaksi #{{ $payment->id }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6">Belum ada langganan yang cocok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($subscriptions->hasPages())
                <div class="admin-pagination">{{ $subscriptions->links() }}</div>
            @endif
        </section>
    </section>
@endsection
