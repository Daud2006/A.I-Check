@extends('layouts.admin')

@section('title', 'Buat Akun | A.I-Check')

@section('content')
    <section class="admin-page" aria-labelledby="judul-buat-akun">
        <a href="{{ route('admin.users.index') }}" class="admin-back-link">Kembali ke pengguna</a>
        <h1 class="admin-title" id="judul-buat-akun">{{ isset($editingUser) ? 'Edit akun pengguna' : 'Buat akun pengguna' }}</h1>

        <section class="admin-data-panel admin-form-panel">
            <form method="POST" action="{{ isset($editingUser) ? route('admin.users.update', $editingUser->id) : route('admin.users.store') }}" class="admin-plan-form">
                @csrf
                @isset($editingUser)
                    @method('PUT')
                @endisset
                <label class="admin-filter-field">
                    <span class="admin-filter-label">Nama lengkap</span>
                    <input name="name" value="{{ old('name', $editingUser->name ?? '') }}" class="admin-filter-control" minlength="3" maxlength="255" required>
                    @error('name') <span class="admin-form-error">{{ $message }}</span> @enderror
                </label>
                <label class="admin-filter-field">
                    <span class="admin-filter-label">Email</span>
                    <input type="email" name="email" value="{{ old('email', $editingUser->email ?? '') }}" class="admin-filter-control" maxlength="255" required>
                    @error('email') <span class="admin-form-error">{{ $message }}</span> @enderror
                </label>
                @unless(isset($editingUser))
                <label class="admin-filter-field">
                    <span class="admin-filter-label">Kata sandi awal</span>
                    <input type="password" name="password" class="admin-filter-control" minlength="8" autocomplete="new-password" aria-describedby="aturan-kata-sandi" required>
                    <span id="aturan-kata-sandi" class="admin-table-secondary">Minimal 8 karakter, termasuk huruf dan angka.</span>
                    @error('password') <span class="admin-form-error">{{ $message }}</span> @enderror
                </label>
                <label class="admin-filter-field">
                    <span class="admin-filter-label">Ulangi kata sandi awal</span>
                    <input type="password" name="password_confirmation" class="admin-filter-control" minlength="8" autocomplete="new-password" required>
                </label>
                @endunless
                <div class="admin-filter-actions"><button type="submit" class="btn btn-primary">{{ isset($editingUser) ? 'Simpan perubahan' : 'Buat akun' }}</button></div>
            </form>
        </section>
    </section>
@endsection
