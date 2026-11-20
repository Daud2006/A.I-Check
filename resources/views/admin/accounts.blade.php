@extends('admin.app')

@section('title', 'A.I Check | Mengelola Akun')

@section('content')
    <div class="container admin-content">
        <div class="admin-heading">
            <span class="eyebrow">@include('icon', ['name' => 'users', 'class' => 'icon-sm']) Pengelolaan akun</span>
            <h1>Mengelola Akun</h1>
        </div>

        @if(session('status'))
            <div class="admin-status" role="status">{{ session('status') }}</div>
        @endif

        <div class="admin-grid admin-grid-four">
            <article class="admin-card"><span class="admin-card-label">Total Pengguna</span><strong>{{ number_format($totalAccounts) }}</strong></article>
            <article class="admin-card"><span class="admin-card-label">Free</span><strong>{{ number_format($freeAccounts) }}</strong></article>
            <article class="admin-card"><span class="admin-card-label">Premium</span><strong>{{ number_format($premiumAccounts) }}</strong></article>
            <article class="admin-card"><span class="admin-card-label">Dibanned</span><strong>{{ number_format($bannedAccounts) }}</strong></article>
        </div>

        <section class="admin-table-card">
            <form method="GET" action="{{ route('admin.accounts') }}" class="admin-search">
                <label for="account-search">Cari nama atau email</label>
                <div><input id="account-search" type="search" name="search" value="{{ $search }}" placeholder="Nama atau email..."><button type="submit">Cari</button></div>
            </form>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>Nama</th><th>Email</th><th>Jenis Akun</th><th>Status Akun</th><th>Pendaftaran</th><th>Aksi</th></tr></thead>
                    <tbody>
                    @forelse($users as $user)
                        @php $isPremium = $user->hasPremiumAccess(); @endphp
                        <tr>
                            <td>#{{ $user->id }}</td>
                            <td><strong>{{ $user->name }}</strong></td>
                            <td>{{ $user->email }}</td>
                            <td><span class="account-badge {{ $isPremium ? 'premium' : 'free' }}">{{ $isPremium ? 'Premium' : 'Free' }}</span></td>
                            <td><span class="account-badge {{ $user->is_banned ? 'banned' : 'active' }}">{{ $user->is_banned ? 'Dibanned' : 'Aktif' }}</span></td>
                            <td>{{ $user->created_at?->format('d/m/Y') }}</td>
                            <td>
                                <div class="admin-actions">
                                    <a href="{{ route('admin.users.show', $user) }}" class="admin-action detail">Detail</a>
                                    @if($user->is_banned)
                                        <form method="POST" action="{{ route('admin.users.unban', $user) }}" data-confirm-unban>@csrf<button type="submit" class="admin-action unban">Unban Akun</button></form>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.ban', $user) }}" data-confirm-ban>@csrf<button type="submit" class="admin-action ban">Ban Akun</button></form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="admin-table-empty">Tidak ada pengguna ditemukan.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="admin-pagination">{{ $users->links() }}</div>
        </section>
    </div>
@endsection
