<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'A.I Check | Admin')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-admin-login-success="{{ session()->pull('admin_login_success', false) ? 'true' : 'false' }}"
      data-admin-logout-success="{{ session()->pull('admin_logout_success', false) ? 'true' : 'false' }}">
    <header class="admin-topbar">
        <div class="container admin-nav-wrap">
            <a href="{{ route('admin') }}" class="admin-brand" aria-label="A.I Check Admin">
                <span class="tanda-logo">@include('icon', ['name' => 'spark'])</span>
                <span>A.I <strong>Check</strong> <small>Admin</small></span>
            </a>
            <nav class="admin-nav" aria-label="Navigasi Admin">
                <a href="{{ route('admin') }}" class="{{ request()->routeIs('admin') ? 'is-active' : '' }}">Dashboard</a>
                <a href="{{ route('admin.accounts') }}" class="{{ request()->routeIs('admin.accounts', 'admin.users.show') ? 'is-active' : '' }}">Mengelola Akun</a>
            </nav>
            <form method="POST" action="{{ route('logout') }}" data-confirm-admin-logout>
                @csrf
                <button type="submit" class="admin-logout">Logout</button>
            </form>
        </div>
    </header>
    <main class="admin-page">
        @yield('content')
    </main>
</body>
</html>
