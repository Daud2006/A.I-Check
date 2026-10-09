@extends('app')

@section('title', 'A.I Check | Tentang')

@section('content')
    {{-- Informasi tentang aplikasi. --}}
    <section class="about-page">
        <div class="page-container">
            <div class="about-panel">
                <span class="eyebrow" style="background:rgba(255,255,255,.1);color:#bfdbfe">@include('icon', ['name' => 'info', 'class' => 'icon-sm'])Tentang A.I Check</span>
                <h1 class="about-title">Sedang dipikirkan</h1>
                <p class="about-copy"></p>
            </div>

        </div>
    </section>
@endsection
