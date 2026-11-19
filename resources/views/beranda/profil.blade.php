@extends('app')

@section('title', 'A.I Check | Profil')

@section('content')
    <section class="profile-page">
        <div class="container">
            <div class="profile-card">
                <div class="profile-head">
                    <div class="profile-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
                    <div>
                        <h1 class="profile-title">Profil Saya</h1>
                    </div>
                </div>
                @if (session('status'))
                    <p class="profile-notice profile-notice-success" role="status" aria-live="polite">{{ session('status') }}</p>
                @endif
                @if ($errors->any())
                    <div class="profile-notice profile-notice-error" role="alert">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif
                <section class="profile-account-info" aria-labelledby="profile-account-title">
                    <h2 class="profile-section-title" id="profile-account-title">Informasi Akun</h2>
                    <form class="profile-form" method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PUT')
                        <label class="form-field">
                            <span class="form-label">Nama lengkap</span>
                            <input class="form-control" name="name" value="{{ old('name', auth()->user()->name) }}"
                                required minlength="3" maxlength="255" autocomplete="name">
                        </label>
                        <dl class="profile-email">
                            <dt>Email</dt>
                            <dd>{{ auth()->user()->email }}</dd>
                        </dl>
                        <button type="submit" class="btn btn-primary">Simpan nama</button>
                    </form>
                </section>

                <section class="profile-account-info" aria-labelledby="profile-subscription-title">
                    <h2 class="profile-section-title" id="profile-subscription-title">Langganan dan kuota</h2>
                    <dl class="profile-account-list">
                        <div class="profile-account-row">
                            <dt>Paket saat ini</dt>
                            <dd>
                                <span class="profile-account-badge {{ $account['quota']['premium'] ? 'is-premium' : 'is-free' }}">
                                    {{ $account['subscription']?->planName() ?? 'Free' }}
                                </span>
                            </dd>
                        </div>
                        @include('plans.account-usage')
                        @if ($nextQuotaAt && $account['quota']['limit'] > 0)
                            <div class="profile-account-row">
                                <dt>Pembaruan kuota berikutnya</dt>
                                <dd><time data-local-date-time datetime="{{ $nextQuotaAt->toIso8601String() }}">{{ $nextQuotaAt->translatedFormat('j F Y, H.i') }} UTC</time></dd>
                            </div>
                        @endif
                    </dl>
                    <div class="profile-subscription-actions">
                        <a href="{{ route('plans.index') }}" class="tautan-dokumen">Lihat paket</a>
                        <a href="{{ route('transactions.index') }}" class="tautan-dokumen">Riwayat transaksi</a>
                    </div>
                </section>
            </div>
        </div>
    </section>
@endsection
