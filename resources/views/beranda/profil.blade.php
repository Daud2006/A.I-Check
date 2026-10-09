@extends('app')

@section('title', 'A.I Check | Profil')

@section('content')
    {{-- Halaman profil memakai gaya visual yang sama dengan halaman lainnya. --}}
    <section class="profile-page"><div class="container"><div class="profile-card"><div class="profile-head"><div class="profile-avatar">A</div><div><h1 class="profile-title">Profil Saya</h1><p class="profile-copy">Informasi akun</p></div></div><form class="profile-form"><label class="form-field"><span class="form-label">Nama Lengkap</span><input class="form-control" value="Pengguna A.I Check"></label><label class="form-field"><span class="form-label">Email</span><input class="form-control" type="email" value="user@example.com"></label><button type="button" class="btn btn-primary">Simpan Perubahan</button></form></div></div></section>
@endsection
