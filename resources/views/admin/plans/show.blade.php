@extends('layouts.admin')

@section('title', 'A.I Check | Detail Paket')

@section('content')
    <section class="admin-page" aria-labelledby="admin-plan-detail-title">
        <div class="admin-user-detail-top">
            <a href="{{ route('admin.plans.index') }}" class="admin-back-link">Kembali ke paket</a>
            <span class="admin-badge {{ $plan->is_active ? 'is-premium' : '' }}">{{ $plan->is_active ? 'Aktif' : 'Nonaktif' }}</span>
        </div>
        @if (session('status'))
            <div class="profile-notice" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="admin-analysis-validation" role="alert">
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="admin-user-profile-heading">
            <span class="admin-user-profile-avatar" aria-hidden="true">@include('icon', ['name' => 'layers'])</span>
            <div>
                <span class="eyebrow">Paket berlangganan</span>
                <h1 class="admin-title" id="admin-plan-detail-title">{{ $plan->name }}</h1>
                <p class="admin-description">{{ $plan->slug }}</p>
            </div>
            <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-secondary">Edit paket</a>
        </div>

        <div class="admin-user-detail-grid">
            <section class="admin-data-panel" aria-labelledby="admin-plan-info-title">
                <div class="admin-section-heading"><div><h2 class="admin-section-title" id="admin-plan-info-title">Informasi paket</h2></div></div>
                <dl class="admin-user-info-list">
                    <div><dt>Deskripsi</dt><dd>{{ $plan->description ?: 'Tidak ada deskripsi' }}</dd></div>
                    <div><dt>Harga saat ini</dt><dd>{{ $plan->currency }} {{ number_format((float) $plan->price, 2, ',', '.') }}</dd></div>
                    <div><dt>Periode</dt><dd>{{ match ($plan->billing_interval) {'monthly' => 'Bulanan', 'yearly' => 'Tahunan', 'none' => 'Tidak berulang', default => $plan->billing_interval} }}</dd></div>
                    <div><dt>Batas analisis</dt><dd>{{ $plan->analysis_limit === null ? 'Tidak diatur' : number_format($plan->analysis_limit) }}</dd></div>
                    <div><dt>Subscription terkait</dt><dd>{{ number_format($plan->subscriptions_count) }}</dd></div>
                    <div><dt>Transaksi terkait</dt><dd>{{ number_format($paymentCount) }}</dd></div>
                    <div><dt>Dibuat</dt><dd>{{ $plan->created_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div><dt>Diperbarui</dt><dd>{{ $plan->updated_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                </dl>
            </section>

            <section class="admin-data-panel" aria-labelledby="admin-plan-status-title">
                <div class="admin-section-heading"><div><h2 class="admin-section-title" id="admin-plan-status-title">Status penjualan</h2></div></div>
                <p class="admin-section-description">Paket yang dinonaktifkan tidak tersedia untuk checkout baru. Paket dengan subscription berbayar aktif atau pembayaran tertunda tidak dapat dinonaktifkan.</p>
                <form method="POST" action="{{ route('admin.plans.active', $plan) }}" class="admin-plan-status-form">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="is_active" value="{{ $plan->is_active ? '0' : '1' }}">
                    <button type="submit" class="btn {{ $plan->is_active ? 'btn-secondary' : 'btn-primary' }}">
                        {{ $plan->is_active ? 'Nonaktifkan paket' : 'Aktifkan paket' }}
                    </button>
                </form>
                <p class="admin-section-description">Paket tidak dapat dihapus agar relasi dan histori transaksi tetap utuh.</p>
            </section>
        </div>
    </section>
@endsection
