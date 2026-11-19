@extends('app')

@section('title', 'A.I Check | Pembayaran Premium')

@section('content')
    <div class="billing-page billing-checkout-page">
        <section class="billing-page-inner billing-checkout" aria-labelledby="checkout-title">
            <a href="{{ route('plans.index') }}" class="tautan-dokumen billing-checkout-back"><span aria-hidden="true">&lt;</span> Kembali ke paket</a>

            <div class="billing-panel">
                <h1 id="checkout-title">{{ $plan->name }}</h1>
                <p class="billing-plan-price">
                    <strong>Rp{{ number_format((float) $plan->price, 2, ',', '.') }}</strong>
                    <span>/{{ $plan->billing_interval === 'yearly' ? 'tahun' : 'bulan' }}</span>
                </p>
                <ul class="billing-plan-benefits">
                    <li>{{ $plan->analysis_limit === null ? 'Analisis tanpa batas' : number_format($plan->analysis_limit, 0, ',', '.').' analisis dalam 7 hari berjalan' }}</li>
                    <li>Prioritas di antrean analisis</li>
                </ul>

                @if ($errors->has('checkout'))
                    <p role="alert">{{ $errors->first('checkout') }}</p>
                @endif

                @if ($paymentAvailable)
                    <form class="billing-checkout-form" method="POST" action="{{ route('checkout.store', $plan) }}">
                        @csrf
                        <fieldset>
                            <legend>Metode pembayaran</legend>
                            <div class="billing-payment-options">
                                @foreach ($paymentMethods as $method => $label)
                                    <label>
                                        <input type="radio" name="payment_method" value="{{ $method }}" @checked(old('payment_method') === $method) required>
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="billing-payment-instruction" data-method="other_qris">Klik <strong>Lanjut bayar</strong> untuk menampilkan kode QRIS di Midtrans, lalu pindai dengan aplikasi pembayaranmu.</p>
                            <p class="billing-payment-instruction" data-method="dana">Klik <strong>Lanjut bayar</strong>, lalu ikuti petunjuk di aplikasi atau halaman DANA. Masukkan nomor akun DANA jika diminta di sana.</p>
                            @error('payment_method')
                                <p role="alert">Pilih DANA atau QRIS.</p>
                            @enderror
                        </fieldset>
                        <button type="submit" class="btn btn-primary">Lanjut bayar</button>
                    </form>
                @else
                    <p role="status">Pembayaran Premium belum tersedia saat ini.</p>
                @endif
            </div>
        </section>
    </div>
@endsection
