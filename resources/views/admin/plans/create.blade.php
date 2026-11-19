@extends('layouts.admin')

@section('title', 'A.I Check | Tambah Paket')

@section('content')
    <section class="admin-page" aria-labelledby="admin-plan-create-title">
        <a href="{{ route('admin.plans.index') }}" class="admin-back-link">Kembali ke paket</a>
        <div class="admin-page-heading">
            <div>

                <h1 class="admin-title" id="admin-plan-create-title">Tambah paket</h1>
                <p class="admin-description">Data paket disimpan sesuai kolom yang tersedia pada database.</p>
            </div>
        </div>
        @include('admin.plans._form', ['plan' => $plan])
    </section>
@endsection
