@extends('layouts.admin')

@section('title', 'A.I Check | Edit Paket')

@section('content')
    <section class="admin-page" aria-labelledby="admin-plan-edit-title">
        <a href="{{ route('admin.plans.show', $plan) }}" class="admin-back-link">Kembali ke detail paket</a>
        <div class="admin-page-heading">
            <div>

                <h1 class="admin-title" id="admin-plan-edit-title">Edit {{ $plan->name }}</h1>
                <p class="admin-description">Perubahan harga tidak mengubah jumlah pembayaran historis.</p>
            </div>
        </div>
        @include('admin.plans._form', ['plan' => $plan])
    </section>
@endsection
