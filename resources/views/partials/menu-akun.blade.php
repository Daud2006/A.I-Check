<details class="menu-akun" data-account-menu>
    <summary class="user-chip">
        <span class="user-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(trim($accountUser->name), 0, 1)) }}</span>
        <span class="user-meta" title="{{ $accountUser->name }}">{{ $accountUser->name }}</span>
        <span class="panah-menu-akun" aria-hidden="true"></span>
    </summary>

    <div class="panel-menu-akun">
        <a href="{{ route('profile') }}" @if (request()->routeIs('profile')) aria-current="page" @endif>Profil</a>
        <a href="{{ route('transactions.index') }}" @if (request()->routeIs('transactions.index')) aria-current="page" @endif>Riwayat Transaksi</a>
        <form method="POST" action="{{ route('logout') }}" data-confirm-logout>
            @csrf
            <button type="submit">
                @include('icon', ['name' => 'logout', 'class' => 'icon-sm'])
                Keluar
            </button>
        </form>
    </div>
</details>
