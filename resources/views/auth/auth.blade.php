@extends('layouts.app')

@section('title', 'A.I Check | Autentikasi')

@section('content')
    {{-- Dedicated auth route: opens the same combined login/register interface used in the Figma header. --}}
    <section class="profile-page"><div class="container"><div class="profile-card"><span class="eyebrow"><x-icon name="lock" class="icon-sm" />Secure access</span><h1 class="profile-title" style="margin-top:16px">Masuk ke A.I Check</h1><p class="profile-copy">Gunakan tombol Masuk pada navigasi untuk membuka modal autentikasi yang sesuai desain.</p><button type="button" class="btn btn-primary" style="margin-top:24px" data-auth-open="login">Buka Login</button></div></div></section>
@endsection
