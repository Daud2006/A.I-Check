@extends('app')

@section('title', 'Paket A.I-Check')

@section('content')
    <div class="billing-page">
        <section class="billing-page-inner" aria-labelledby="plans-title">
            <header class="billing-heading">
                <h1 id="plans-title">Langganan A.I-Check</h1>
            </header>

            @if ($account)
                <section class="billing-panel billing-account-summary" aria-label="Langganan Anda">
                    <p>Paket saat ini <strong>{{ $account['subscription']?->planName() ?? 'Free' }}</strong></p>
                    <dl class="profile-account-list">
                        @include('plans.account-usage')
                    </dl>
                    <a href="{{ route('transactions.index') }}" class="billing-link">Riwayat transaksi</a>
                </section>
            @endif

            @if ($errors->has('checkout'))
                <div class="profile-notice profile-notice-error" role="alert">
                    <p>{{ $errors->first('checkout') }}</p>
                </div>
            @endif

            @if (! $midtransConfigured)
                <div class="profile-notice profile-notice-error" role="status">
                    <p>Pembayaran Premium belum tersedia saat ini.</p>
                </div>
            @endif

            <div class="billing-plan-grid">
                <article class="billing-plan-card">
                    <h2>Free</h2>
                    <p class="billing-plan-price"><strong>Rp0</strong></p>
                    <ul class="billing-plan-benefits">
                        <li><strong>{{ $freeLimit }} analisis</strong> dalam 7 hari berjalan</li>
                        <li>Antrean reguler</li>
                    </ul>
                    <a href="{{ route('checker') }}" class="btn btn-secondary">Buka Deteksi Gambar</a>
                </article>

                @if ($plans->isNotEmpty())
                    @foreach ($plans as $plan)
                        <article class="billing-plan-card billing-plan-card-premium">
                            <h2>{{ $plan->name }}</h2>
                            @if ($plan->description)
                                <p>{{ $plan->description }}</p>
                            @endif
                            <p class="billing-plan-price">
                                <strong>{{ $plan->currency }} {{ number_format((float) $plan->price, 2, ',', '.') }}</strong>
                                <span>/{{ $plan->billing_interval === 'yearly' ? 'tahun' : 'bulan' }}</span>
                            </p>
                            <ul class="billing-plan-benefits">
                                <li>
                                    @if ($plan->analysis_limit === null)
                                        <strong>Analisis tanpa batas kuota</strong>
                                    @else
                                        <strong>{{ number_format($plan->analysis_limit, 0, ',', '.') }} analisis</strong> dalam 7 hari berjalan
                                    @endif
                                </li>
                            </ul>

                            <a href="{{ route('checkout.show', $plan) }}" class="btn btn-primary">Beli</a>
                        </article>
                    @endforeach
                @else
                    <div class="billing-panel billing-empty">
                        <h2>Paket Premium belum tersedia</h2>
                        <p>Anda dapat mulai menggunakan paket Free.</p>
                    </div>
                @endif
            </div>

            @if ($plans->isNotEmpty())
                <p class="billing-payment-note">Prioritas di antrean analisis berlaku untuk semua paket Premium.</p>
            @endif
            <p class="billing-payment-note">Semua paket mendukung unggahan hingga 10 MB, riwayat, dan cetak laporan.</p>

            @if ($plans->isNotEmpty() && $midtransConfigured)
                <p class="billing-payment-note">
                    Bayar sekali per periode dengan DANA atau QRIS. Premium aktif setelah pembayaran terverifikasi.
                </p>
            @endif
        </section>
    </div>
@endsection
