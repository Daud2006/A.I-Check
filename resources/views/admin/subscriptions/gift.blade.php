@extends('layouts.admin')

@section('title', 'Gift Paket | A.I-Check')

@section('content')
    <section class="admin-page" aria-labelledby="judul-hadiah">
        <a href="{{ route('admin.subscriptions.index') }}" class="admin-back-link">Kembali ke langganan</a>
        <h1 class="admin-title" id="judul-hadiah">Gift Paket</h1>

        <section class="admin-data-panel admin-users-filter-panel">
            <form action="{{ route('admin.plans.gift.create') }}" method="GET" class="admin-analysis-filters">
                <label class="admin-filter-field admin-search-field">
                    <span class="admin-filter-label">Nama atau email penerima</span>
                    <input name="search" type="search" value="{{ $search }}" class="admin-filter-control" maxlength="100" required>
                </label>
                <div class="admin-filter-actions"><button type="submit" class="btn btn-secondary">Cari pengguna</button></div>
            </form>
        </section>

        @if ($errors->any())
            <div class="admin-analysis-validation" role="alert">{{ $errors->first() }}</div>
        @endif

        @if ($search !== '' && $users->isEmpty())
            <p role="status">Pengguna tidak ditemukan.</p>
        @elseif ($users->isNotEmpty() && $plans->isNotEmpty())
            <section class="admin-data-panel admin-form-panel">
                <form action="{{ route('admin.plans.gift.store') }}" method="POST" class="admin-plan-form">
                    @csrf
                    <input type="hidden" name="reference" value="{{ $reference }}">
                    <label class="admin-filter-field">
                        <span class="admin-filter-label">Penerima hadiah</span>
                        <select name="user_id" class="admin-filter-control" required>
                            <option value="">Pilih akun</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected((string) old('user_id') === (string) $user->id)>{{ $user->name }} · {{ $user->email }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="admin-filter-field">
                        <span class="admin-filter-label">Paket untuk satu periode</span>
                        <select name="plan_id" class="admin-filter-control" required>
                            <option value="">Pilih paket</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected((string) old('plan_id') === (string) $plan->id)>{{ $plan->name }} · {{ $plan->billing_interval === 'yearly' ? '1 tahun' : '1 bulan' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <p class="admin-plan-description">Hadiah dimulai setelah masa paket yang sudah tercatat berakhir. Penerima tidak ditagih.</p>
                    <label class="cek-formulir admin-plan-description"><input type="checkbox" name="confirm" value="1" required> Saya sudah memeriksa penerima dan paket.</label>
                    <div class="admin-filter-actions"><button type="submit" class="btn btn-primary">Kirim hadiah</button></div>
                </form>
            </section>
        @elseif ($plans->isEmpty())
            <p role="status">Belum ada paket Premium aktif yang dapat dihadiahkan.</p>
        @endif
    </section>
@endsection
