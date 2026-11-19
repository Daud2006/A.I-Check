@extends('app')

@section('title', 'Paket A.I-Check')

@section('content')
    <main class="billing-page">
        <section class="billing-page-inner" aria-labelledby="plans-title">
            <header class="billing-heading">
                <span class="eyebrow">Langganan A.I-Check</span>
                <h1 id="plans-title">Pilih paket</h1>
                <p>Harga dan periode di bawah ini berasal dari paket aktif di database.</p>
            </header>

            @if ($errors->has('checkout'))
                <div class="profile-notice profile-notice-error" role="alert">
                    <p>{{ $errors->first('checkout') }}</p>
                </div>
            @endif

            @if (! $midtransConfigured)
                <div class="profile-notice profile-notice-error" role="status">
                    <p>Pembayaran belum tersedia. Konfigurasi Midtrans Sandbox harus disiapkan oleh administrator.</p>
                </div>
            @endif

            @if ($plans->isNotEmpty())
                <div class="billing-plan-grid">
                    @foreach ($plans as $plan)
                        <article class="billing-plan-card">
                            <span class="eyebrow">{{ $plan->billing_interval === 'yearly' ? 'TAHUNAN' : 'BULANAN' }}</span>
                            <h2>{{ $plan->name }}</h2>
                            @if ($plan->description)
                                <p>{{ $plan->description }}</p>
                            @endif
                            <p class="billing-plan-price">
                                <strong>{{ $plan->currency }} {{ number_format((float) $plan->price, 2, ',', '.') }}</strong>
                                <span>/{{ $plan->billing_interval === 'yearly' ? 'tahun' : 'bulan' }}</span>
                            </p>

                            @auth
                                <form method="POST" action="{{ route('checkout.store', $plan->id) }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                        @disabled(! $midtransConfigured)
                                    >
                                        Lanjutkan ke pembayaran
                                    </button>
                                </form>
                            @else
                                <a href="{{ route('auth') }}" class="btn btn-primary">Masuk untuk membeli</a>
                            @endauth
                        </article>
                    @endforeach
                </div>
            @else
                <div class="billing-empty" role="status">
                    <h2>Belum ada paket berbayar tersedia</h2>
                    <p>Paket aktif berharga dalam IDR dengan periode bulanan atau tahunan akan tampil di sini.</p>
                </div>
            @endif

            <p class="billing-payment-note">
                Pengaktifan subscription dilakukan setelah notifikasi pembayaran Midtrans diverifikasi server.
                Jika Anda kembali dari Midtrans lebih dahulu, periksa status transaksi pada riwayat pembayaran.
            </p>
        </section>
    </main>
@endsection
