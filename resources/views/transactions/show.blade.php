@extends('app')

@section('title', 'Detail Transaksi')

@section('content')
    <main class="billing-page">
        <div class="billing-page-inner">
            <a href="{{ route('transactions.index') }}" class="billing-back-link">Kembali ke transaksi</a>
            <header class="billing-heading">
                <span class="eyebrow">Pembayaran</span>
                <h1>Transaksi #{{ $payment->id }}</h1>
                <p>Dibuat {{ $payment->created_at?->format('d/m/Y H:i:s') ?? 'Waktu tidak tersedia' }}</p>
            </header>

            <section class="billing-panel billing-detail-panel" aria-label="Detail transaksi">
                <dl class="billing-details">
                    <div><dt>Paket</dt><dd>{{ $payment->subscription?->planName() ?? 'Paket tidak tersedia' }}</dd></div>
                    <div><dt>Jumlah</dt><dd>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2, ',', '.') }}</dd></div>
                    <div>
                        <dt>Status pembayaran</dt>
                        <dd>
                            {{ match ($payment->status) {
                                'pending' => 'Menunggu pembayaran',
                                'paid' => 'Berhasil',
                                'failed' => 'Gagal',
                                'refunded' => 'Dikembalikan',
                                default => 'Tidak diketahui',
                            } }}
                        </dd>
                    </div>
                    <div><dt>Metode pembayaran</dt><dd>Tidak tersedia pada data transaksi</dd></div>
                    <div><dt>ID transaksi Midtrans</dt><dd>{{ $payment->provider_transaction_id ?? 'Belum tersedia' }}</dd></div>
                    <div><dt>Waktu pembayaran</dt><dd>{{ $payment->paid_at?->format('d/m/Y H:i:s') ?? 'Belum dibayar' }}</dd></div>
                    <div><dt>Status langganan</dt><dd>{{ $payment->subscription?->status ?? 'Tidak tersedia' }}</dd></div>
                    <div><dt>Masa langganan</dt><dd>
                        @if ($payment->subscription?->starts_at)
                            {{ $payment->subscription->starts_at->format('d/m/Y') }}
                            sampai
                            {{ $payment->subscription->ends_at?->format('d/m/Y') ?? 'tanpa tanggal akhir' }}
                        @else
                            Belum diaktifkan
                        @endif
                    </dd></div>
                </dl>
                @if ($checkoutUrl)
                    <p><a class="btn btn-primary" href="{{ $checkoutUrl }}">Lanjutkan pembayaran</a></p>
                @endif
            </section>
        </div>
    </main>
@endsection
