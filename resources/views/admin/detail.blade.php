@extends('admin.app')

@section('title', 'A.I Check | Detail Pengguna')

@section('content')
    <div class="container admin-content">
        <a href="{{ route('admin.accounts') }}" class="admin-back">&larr; Kembali ke daftar akun</a>
        <section class="admin-detail-card">
            <div class="admin-detail-head">
                <div><span class="eyebrow">Detail pengguna</span><h1>{{ $user->name }}</h1></div>
                <span class="account-badge {{ $user->is_banned ? 'banned' : 'active' }}">{{ $user->is_banned ? 'Dibanned' : 'Aktif' }}</span>
            </div>
            <dl class="admin-detail-grid">
                <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
                <div><dt>ID Akun</dt><dd>#{{ $user->id }}</dd></div>
                <div><dt>Tanggal Pendaftaran</dt><dd>{{ $user->created_at?->format('d/m/Y H:i') }}</dd></div>
                <div><dt>Jenis Akun</dt><dd>{{ $isPremium ? 'Premium' : 'Free' }}</dd></div>
                <div><dt>Status Langganan</dt><dd>{{ $user->subscriptions->where('status', 'active')->first()?->status ?? 'Tidak ada langganan aktif' }}</dd></div>
                <div><dt>Status Akun</dt><dd>{{ $user->is_banned ? 'Dibanned' : 'Aktif' }}</dd></div>
                <div><dt>Tanggal Ban</dt><dd>{{ $user->banned_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
                <div><dt>Alasan Ban</dt><dd>{{ $user->ban_reason ?: '-' }}</dd></div>
                <div><dt>Dipulihkan Pada</dt><dd>{{ $user->unbanned_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
            </dl>
        </section>
    </div>
@endsection
