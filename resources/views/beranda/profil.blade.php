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
                        <p class="profile-copy">Informasi akun</p>
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
                <form class="profile-form" method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PUT')
                    <label class="form-field">
                        <span class="form-label">Nama Lengkap</span>
                        <input class="form-control" name="name" value="{{ old('name', auth()->user()->name) }}"
                            required minlength="3" maxlength="255" autocomplete="name">
                    </label>
                    <label class="form-field">
                        <span class="form-label">Email</span>
                        <input class="form-control" type="email" name="email"
                            value="{{ old('email', auth()->user()->email) }}" required maxlength="255"
                            autocomplete="email">
                    </label>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </form>
            </div>
        </div>
    </section>
@endsection
