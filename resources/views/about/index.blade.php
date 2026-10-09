@extends('layouts.app')

@section('title', 'A.I Check | Tentang')

@section('content')
    {{-- About page matching the Figma blue information panel. --}}
    <section class="about-page">
        <div class="page-container">
            <div class="about-panel">
                <span class="eyebrow" style="background:rgba(255,255,255,.1);color:#bfdbfe"><x-icon name="info" class="icon-sm" />Tentang A.I Check</span>
                <h1 class="about-title">Sedang dipikirkan</h1>
                <p class="about-copy"></p>
            </div>

        </div>
    </section>
@endsection
