@extends('layouts.admin')

@section('title', 'A.I Check | Detail Transaksi')

@section('content')
    <section class="admin-page" aria-labelledby="admin-transaction-detail-title">
        <div class="admin-user-detail-top">
            <a href="{{ route('admin.transactions.index') }}" class="admin-back-link">
                @include('icon', ['name' => 'arrow', 'class' => 'icon-sm'])
                Kembali ke transaksi
            </a>
            <span class="admin-badge {{ $payment->status === 'paid' ? 'is-premium' : '' }}">
                {{ match ($payment->status) {
                    'pending' => 'Menunggu',
                    'paid' => 'Berhasil',
                    'failed' => 'Gagal',
                    'refunded' => 'Dikembalikan',
                    default => 'Tidak diketahui',
                } }}
            </span>
        </div>

        <div class="admin-user-profile-heading">
            <span class="admin-user-profile-avatar" aria-hidden="true">@include('icon', ['name' => 'check'])</span>
            <div>

                <h1 class="admin-title" id="admin-transaction-detail-title">Transaksi #{{ $payment->id }}</h1>
                <p class="admin-description">{{ $payment->created_at?->format('d/m/Y H:i:s') ?? 'Waktu tidak tersedia' }}</p>
            </div>
        </div>

        <div class="admin-user-detail-grid">
            <section class="admin-data-panel" aria-labelledby="admin-transaction-payment-title">
                <div class="admin-section-heading">
                    <div>
                        <h2 class="admin-section-title" id="admin-transaction-payment-title">Informasi pembayaran</h2>
                        <p class="admin-section-description">Status hanya diperbarui melalui notifikasi provider terverifikasi.</p>
                    </div>
                </div>
                <dl class="admin-user-info-list">
                    <div><dt>Jumlah</dt><dd>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2, ',', '.') }}</dd></div>
                    <div><dt>Provider</dt><dd>{{ $payment->provider ?? 'Tidak tersedia' }}</dd></div>
                    <div><dt>ID pesanan Midtrans</dt><dd>{{ $payment->provider === 'midtrans' ? 'AIC-PAY-'.$payment->id : '—' }}</dd></div>
                    <div><dt>ID transaksi Midtrans</dt><dd>{{ $payment->provider_transaction_id ?? 'Belum tersedia' }}</dd></div>
                    <div><dt>Status</dt><dd>{{ $payment->status }}</dd></div>
                    <div><dt>Waktu dibayar</dt><dd>{{ $payment->paid_at?->format('d/m/Y H:i:s') ?? 'Belum dibayar' }}</dd></div>
                    <div><dt>Metode pembayaran</dt><dd>{{ match ($payment->payment_method) {'dana' => 'DANA', 'qris' => 'QRIS', default => 'Belum tercatat'} }}</dd></div>
                    <div><dt>Lingkungan</dt><dd>{{ match ($payment->environment) {'production' => 'Produksi', 'sandbox' => 'Sandbox', default => 'Belum tercatat'} }}</dd></div>
                    <div><dt>Webhook terverifikasi</dt><dd>{{ $payment->verified_at?->format('d/m/Y H:i:s') ?? 'Belum tercatat' }}</dd></div>
                </dl>
            </section>

            <section class="admin-data-panel" aria-labelledby="admin-transaction-owner-title">
                <div class="admin-section-heading">
                    <div>
                        <h2 class="admin-section-title" id="admin-transaction-owner-title">Pengguna dan subscription</h2>
                        <p class="admin-section-description">Data terkait transaksi.</p>
                    </div>
                </div>
                <dl class="admin-user-info-list">
                    <div><dt>Nama pengguna</dt><dd>{{ $payment->user?->name ?? 'Pengguna tidak tersedia' }}</dd></div>
                    <div><dt>Email</dt><dd>{{ $payment->user?->email ?? 'Tidak tersedia' }}</dd></div>
                    <div><dt>Paket</dt><dd>{{ $payment->subscription?->planName() ?? 'Tidak tersedia' }}</dd></div>
                    <div><dt>Status subscription</dt><dd>{{ $payment->subscription?->status ?? 'Tidak tersedia' }}</dd></div>
                    <div><dt>Mulai</dt><dd>{{ $payment->subscription?->starts_at?->format('d/m/Y H:i') ?? 'Belum tersedia' }}</dd></div>
                    <div><dt>Berakhir</dt><dd>{{ $payment->subscription?->ends_at?->format('d/m/Y H:i') ?? 'Belum tersedia' }}</dd></div>
                </dl>
            </section>
        </div>
    </section>
@endsection
