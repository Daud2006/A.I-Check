@extends('layouts.admin')

@section('title', 'A.I Check | Manajemen Pengguna')

@section('content')
    <section class="admin-page admin-page-wide" aria-labelledby="admin-users-title">
        <div class="admin-page-heading admin-users-heading">
            <div>

                <h1 class="admin-title" id="admin-users-title">Manajemen Pengguna</h1>

            </div>
        </div>

        @if (session('status'))
            <p class="admin-status-note" role="status">{{ session('status') }}</p>
        @endif

        <section class="admin-data-panel admin-users-filter-panel" aria-label="Pencarian dan filter pengguna">
            <form method="GET" action="{{ route('admin.users.index') }}" class="admin-user-filters">
                <label class="admin-filter-field admin-search-field">
                    <span class="admin-filter-label">Cari pengguna</span>
                    <input
                        type="search"
                        name="search"
                        value="{{ $filters['search'] }}"
                        class="admin-filter-control"
                        placeholder="Nama atau email"
                        maxlength="100"
                    >
                </label>

                <label class="admin-filter-field">
                    <span class="admin-filter-label">Role</span>
                    <select name="role" class="admin-filter-control">
                        <option value="">Semua role</option>
                        <option value="user" @selected($filters['role'] === 'user')>Pengguna</option>
                        <option value="admin" @selected($filters['role'] === 'admin')>Admin</option>
                    </select>
                </label>

                <label class="admin-filter-field">
                    <span class="admin-filter-label">Status langganan</span>
                    <select name="subscription" class="admin-filter-control">
                        <option value="">Semua status</option>
                        <option value="none" @selected($filters['subscription'] === 'none')>Tanpa subscription</option>
                        @foreach (\App\Models\Subscription::STATUSES as $status)
                            <option value="{{ $status }}" @selected($filters['subscription'] === $status)>
                                {{ match ($status) {
                                    'active' => 'Aktif',
                                    'pending' => 'Menunggu',
                                    'expired' => 'Kedaluwarsa',
                                    'cancelled' => 'Dibatalkan',
                                    default => ucfirst($status),
                                } }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="admin-filter-field">
                    <span class="admin-filter-label">Paket</span>
                    <select name="plan" class="admin-filter-control">
                        <option value="">Semua paket</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected($filters['plan'] === (string) $plan->id)>
                                {{ $plan->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="admin-filter-field">
                    <span class="admin-filter-label">Akses saat ini</span>
                    <select name="account" class="admin-filter-control">
                        <option value="">Semua akun</option>
                        <option value="free" @selected($filters['account'] === 'free')>Free</option>
                        <option value="premium" @selected($filters['account'] === 'premium')>Premium</option>
                    </select>
                </label>

                <div class="admin-filter-actions">
                    <a href="{{ route('admin.users.create') }}" class="btn btn-secondary">Buat akun</a>
                    <button type="submit" class="btn btn-primary">
                        @include('icon', ['name' => 'search', 'class' => 'icon-sm'])
                        Terapkan
                    </button>
                        <a href="{{ route('admin.users.index') }}" class="btn btn-admin-reset">Reset filter</a>
                </div>
            </form>
        </section>

        <section class="admin-data-panel admin-users-table-panel" aria-label="Daftar pengguna">
            @if ($users->isNotEmpty())
                <div class="admin-users-table-scroll">
                    <table class="admin-table admin-users-table">
                        <thead>
                            <tr>
                                <th scope="col">No.</th>
                                <th scope="col">Pengguna</th>
                                <th scope="col">Role</th>
                                <th scope="col">Tanggal registrasi</th>
                                <th scope="col">Status subscription</th>
                                <th scope="col">Paket</th>
                                <th scope="col" class="admin-sticky-actions"><span class="admin-visually-hidden">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                @php($subscription = $user->latestSubscription)
                                <tr>
                                    <td>{{ $users->firstItem() + $loop->index }}</td>
                                    <td>
                                        <span class="admin-table-primary">{{ $user->name }}</span>
                                        <span class="admin-table-secondary">{{ $user->email }}</span>
                                        @if ($user->disabled_at)
                                            <span class="admin-table-secondary">Akun nonaktif</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="admin-badge {{ $user->role === \App\Models\User::ROLE_ADMIN ? 'is-admin' : '' }}">
                                            {{ $user->role === \App\Models\User::ROLE_ADMIN ? 'Admin' : 'Pengguna' }}
                                        </span>
                                    </td>
                                    <td>{{ $user->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td>
                                        @if ($subscription)
                                            <span class="admin-badge {{ $subscription->status === 'active' ? 'is-premium' : '' }}">
                                                {{ match ($subscription->status) {
                                                    'active' => 'Aktif',
                                                    'pending' => 'Menunggu',
                                                    'expired' => 'Kedaluwarsa',
                                                    'cancelled' => 'Dibatalkan',
                                                    default => 'Tidak diketahui',
                                                } }}
                                            </span>
                                        @else
                                            <span class="admin-table-secondary">Tidak ada</span>
                                        @endif
                                    </td>
                                    <td>{{ $subscription?->planName() ?? '—' }}</td>
                                    <td class="admin-sticky-actions">
                                        <div class="admin-row-actions">
                                            @if ($user->role === \App\Models\User::ROLE_USER && ! $user->disabled_at)
                                                <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}" data-confirm-admin-action="Hapus akun {{ $user->name }}? Akun yang memiliki riwayat akan dinonaktifkan.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="admin-row-delete" title="Hapus akun {{ $user->name }}" aria-label="Hapus akun {{ $user->name }}">
                                                        @include('icon', ['name' => 'trash', 'class' => 'icon-sm'])
                                                    </button>
                                                </form>
                                            @endif
                                            <a
                                                href="{{ route('admin.users.show', $user->id) }}"
                                                class="admin-user-detail-link"
                                                aria-label="Lihat detail {{ $user->name }}"
                                            >
                                                Lihat detail
                                                @include('icon', ['name' => 'arrow', 'class' => 'icon-sm'])
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <nav class="admin-pagination" aria-label="Navigasi halaman pengguna">
                        <span class="admin-pagination-summary">
                            Menampilkan {{ $users->firstItem() }}–{{ $users->lastItem() }} dari {{ $users->total() }}
                        </span>
                        {{ $users->links() }}
                    </nav>
                @endif
            @else
                <div class="admin-empty-state admin-users-empty">
                    <span class="admin-empty-icon">@include('icon', ['name' => 'user'])</span>
                    <h2>{{ collect($filters)->contains(fn (string $value): bool => $value !== '') ? 'Pengguna tidak ditemukan' : 'Belum ada pengguna' }}</h2>
                    <p>
                        {{ collect($filters)->contains(fn (string $value): bool => $value !== '')
                            ? 'Coba ubah kata pencarian atau reset filter untuk melihat hasil lain.'
                            : 'Akun pengguna yang terdaftar akan muncul di halaman ini.' }}
                    </p>
                    @if (collect($filters)->contains(fn (string $value): bool => $value !== ''))
                        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Reset pencarian dan filter</a>
                    @endif
                </div>
            @endif
        </section>
    </section>
@endsection
