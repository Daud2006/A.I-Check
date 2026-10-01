@extends('layouts.app')

@section('title', 'A.I Check | Riwayat')

@section('content')
    {{-- Bagian ini menampilkan riwayat, statistik, dan filter untuk pencarian hasil pemeriksaan. --}}
    <section class="halaman-riwayat">
        <div class="container">
            <div class="kepala-halaman-riwayat">
                <div>
                    <span class="eyebrow"><x-icon name="clock" class="icon-sm" />Aktivitas Anda</span>
                    <h1 class="judul-halaman-riwayat">Riwayat analisis lengkap</h1>
                    <p class="deskripsi-halaman-riwayat">Cari, filter, dan buka kembali seluruh hasil pemeriksaan poster.</p>
                </div>
                <button type="button" class="btn btn-danger-outline no-print" id="tombol-hapus-riwayat"><x-icon name="trash" class="icon-sm" />Hapus semua</button>
            </div>

            <div class="kartu-statistik-riwayat">
                <div class="kartu-statistik"><div class="label-statistik">Total analisis</div><div class="nilai-statistik" id="total-analisis">0</div><div class="catatan-statistik">Poster telah diperiksa</div></div>
                <div class="kartu-statistik"><div class="label-statistik">Terdeteksi AI</div><div class="nilai-statistik" id="analisis-ai">0</div><div class="catatan-statistik">Skor di atas 75%</div></div>
                <div class="kartu-statistik"><div class="label-statistik">Rata-rata skor AI</div><div class="nilai-statistik" id="rata-rata-skor">0%</div><div class="catatan-statistik">Dari seluruh analisis</div></div>
            </div>

            <div class="filter-riwayat no-print">
                <label class="pencarian-riwayat"><x-icon name="search" /><input id="pencarian-riwayat" class="form-control" placeholder="Cari nama poster..."></label>
                <select id="filter-riwayat" class="form-control"><option value="all">Semua hasil</option><option value="ai">Kemungkinan AI</option><option value="review">Perlu ditinjau</option><option value="human">Kemungkinan asli</option></select>
                <select id="urutan-riwayat" class="form-control"><option value="newest">Terbaru</option><option value="highest">Skor tertinggi</option><option value="lowest">Skor terendah</option></select>
            </div>

            <div class="daftar-riwayat" id="daftar-riwayat"></div>
        </div>
    </section>

    <div class="modal-detail-riwayat detail-modal" id="modal-detail-riwayat" hidden>
        <div class="panel-detail-riwayat">
            <div class="kepala-detail-riwayat"><div><div class="label-detail-riwayat">Detail analisis</div><h2 class="judul-detail-riwayat" id="judul-detail-riwayat">-</h2></div><button type="button" class="auth-close" id="tombol-tutup-detail"><x-icon name="close" /></button></div>
            <div class="skor-detail-riwayat"><div class="kotak-skor-detail"><div class="label-skor-detail">Probabilitas AI</div><div class="nilai-skor-detail" id="nilai-ai-detail">0%</div></div><div class="kotak-skor-detail netral"><div class="label-skor-detail">Probabilitas manusia</div><div class="nilai-skor-detail" style="color:#10243e" id="nilai-manusia-detail">0%</div></div></div>
            <div class="info-detail-riwayat"><div class="baris-info-detail"><span>Status</span><strong id="status-detail-riwayat">-</strong></div><div class="baris-info-detail"><span>Waktu pemeriksaan</span><strong id="tanggal-detail-riwayat">-</strong></div><div class="baris-info-detail"><span>ID analisis</span><strong id="id-detail-riwayat">-</strong></div></div>
            <div class="peringatan-detail-riwayat"><x-icon name="info" class="icon-sm" />Hasil merupakan estimasi model dan sebaiknya digunakan bersama pemeriksaan sumber.</div>
            <button type="button" class="btn btn-primary tombol-simpan-laporan" onclick="window.print()"><x-icon name="download" class="icon-sm" />Simpan laporan</button>
        </div>
    </div>
@endsection
