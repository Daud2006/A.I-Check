@extends('layouts.app')

@section('title', 'A.I Check | Tentang')

@section('content')
    {{-- About page matching the Figma blue information panel. --}}
    <section class="about-page">
        <div class="page-container">
            <div class="about-panel">
                <span class="eyebrow" style="background:rgba(255,255,255,.1);color:#bfdbfe"><x-icon name="info" class="icon-sm" />Tentang A.I Check</span>
                <h1 class="about-title">Membangun kepercayaan di era konten generatif.</h1>
                <p class="about-copy">A.I Check adalah alat bantu untuk mengidentifikasi karakteristik visual yang sering muncul pada poster buatan AI. Sistem dirancang untuk terhubung dengan model image classification di Hugging Face.</p>
                <div class="about-features">
                    <article class="about-feature"><x-icon name="shield" /><h2>Privasi</h2><p>Gambar diproses hanya untuk keperluan analisis.</p></article>
                    <article class="about-feature"><x-icon name="spark" /><h2>Transparan</h2><p>Skor disajikan sebagai probabilitas, bukan vonis.</p></article>
                    <article class="about-feature"><x-icon name="layers" /><h2>Fleksibel</h2><p>Model Hugging Face dapat diganti melalui konfigurasi API.</p></article>
                </div>
            </div>
            <div class="about-note"><h2>Catatan integrasi Hugging Face</h2><p>Tetapkan environment variable <code>VITE_HF_API_URL</code> ke endpoint inference model Anda. Tanpa endpoint tersebut, aplikasi berjalan dalam mode demonstrasi agar seluruh alur antarmuka tetap dapat diuji.</p></div>
        </div>
    </section>
@endsection
