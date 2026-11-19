<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Area administrasi A.I-Check.">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'A.I Check | Admin')</title>
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
</head>

<body class="admin-body">
    <div class="admin-shell" id="admin-shell">
        <header class="admin-topbar">
            <button
                type="button"
                class="admin-menu-toggle"
                id="admin-sidebar-toggle"
                aria-label="Buka atau perkecil navigasi Admin"
                aria-controls="admin-sidebar"
                aria-expanded="false"
            >
                @include('icon', ['name' => 'menu'])
            </button>

            <a href="{{ route('home') }}" class="admin-brand" aria-label="A.I-Check, kembali ke website">
                <span class="admin-brand-mark">@include('icon', ['name' => 'spark'])</span>
                <span>A.I <span class="admin-brand-accent">Check</span></span>
            </a>

            <div class="admin-topbar-heading">
                <span class="admin-topbar-kicker">A.I-CHECK</span>
                <span class="admin-topbar-title">Admin Dashboard</span>
            </div>

            <div class="admin-account" aria-label="Akun administrator">
                <span class="admin-account-avatar" aria-hidden="true">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                <span class="admin-account-details">
                    <strong>{{ auth()->user()->name }}</strong>
                    <span>Administrator</span>
                </span>
            </div>
        </header>

        <div class="admin-workspace">
            <button
                type="button"
                class="admin-sidebar-backdrop"
                data-admin-sidebar-close
                aria-label="Tutup navigasi Admin"
                tabindex="-1"
            ></button>

            <aside class="admin-sidebar" id="admin-sidebar" aria-label="Navigasi Admin">
                <div class="admin-sidebar-caption">Menu utama</div>
                <nav class="admin-navigation">
                    <a href="{{ route('admin') }}" class="admin-nav-link {{ request()->routeIs('admin') ? 'is-active' : '' }}" @if (request()->routeIs('admin')) aria-current="page" @endif>
                        @include('icon', ['name' => 'layers'])
                        <span class="admin-nav-label">Dashboard</span>
                    </a>

                    <a href="{{ route('admin.users.index') }}" class="admin-nav-link {{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}" @if (request()->routeIs('admin.users.*')) aria-current="page" @endif>
                        @include('icon', ['name' => 'user'])
                        <span class="admin-nav-label">Pengguna</span>
                    </a>
                    <a href="{{ route('admin.analyses.index') }}" class="admin-nav-link {{ request()->routeIs('admin.analyses.*') ? 'is-active' : '' }}" @if (request()->routeIs('admin.analyses.*')) aria-current="page" @endif>
                        @include('icon', ['name' => 'image'])
                        <span class="admin-nav-label">Analisis</span>
                    </a>
                    <a href="{{ route('admin.subscriptions.index') }}" class="admin-nav-link {{ request()->routeIs('admin.subscriptions.*') ? 'is-active' : '' }}" @if (request()->routeIs('admin.subscriptions.index')) aria-current="page" @endif>
                        @include('icon', ['name' => 'refresh'])
                        <span class="admin-nav-label">Langganan</span>
                    </a>
                    <a href="{{ route('admin.transactions.index') }}" class="admin-nav-link {{ request()->routeIs('admin.transactions.*') ? 'is-active' : '' }}" @if (request()->routeIs('admin.transactions.*')) aria-current="page" @endif>
                        @include('icon', ['name' => 'check'])
                        <span class="admin-nav-label">Transaksi</span>
                    </a>
                    <a href="{{ route('admin.reports.index') }}" class="admin-nav-link {{ request()->routeIs('admin.reports.*') ? 'is-active' : '' }}" @if (request()->routeIs('admin.reports.*')) aria-current="page" @endif>
                        @include('icon', ['name' => 'download'])
                        <span class="admin-nav-label">Laporan</span>
                    </a>
                    <a href="{{ route('admin.plans.index') }}" class="admin-nav-link {{ request()->routeIs('admin.plans.*') ? 'is-active' : '' }}" @if (request()->routeIs('admin.plans.*')) aria-current="page" @endif>
                        @include('icon', ['name' => 'layers'])
                        <span class="admin-nav-label">Paket &amp; Kuota</span>
                    </a>
                </nav>

                <div class="admin-sidebar-footer">
                    <a href="{{ route('home') }}" class="admin-nav-link">
                        @include('icon', ['name' => 'home'])
                        <span class="admin-nav-label">Back to Website</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="admin-nav-link admin-logout">
                            @include('icon', ['name' => 'logout'])
                            <span class="admin-nav-label">Logout</span>
                        </button>
                    </form>
                </div>
            </aside>

            <main class="admin-main" id="admin-main">
                @yield('content')
            </main>
        </div>
    </div>
</body>

</html>
