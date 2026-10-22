@extends('app')

@section('title', 'A.I Check | Riwayat')

@section('content')
{{-- Daftar riwayat pemeriksaan. --}}
<section class="halaman-riwayat">
    <div class="container">
        <div class="kepala-halaman-riwayat">
            <div>
                <span class="eyebrow">@include('icon', ['name' => 'clock', 'class' => 'icon-sm'])Aktivitas Anda</span>
                <h1 class="judul-halaman-riwayat">Riwayat analisis lengkap</h1>
                <p class="deskripsi-halaman-riwayat">Cari, filter, dan tinjau kembali analisis yang tersimpan pada akun Anda.
                </p>
            </div>
            <button type="button" class="btn btn-danger-outline no-print" id="tombol-hapus-riwayat">@include('icon',
                ['name' => 'trash', 'class' => 'icon-sm'])Hapus semua</button>
        </div>

        <div class="kartu-statistik-riwayat">
            <div class="kartu-statistik">
                <div class="label-statistik">Total analisis</div>
                <div class="nilai-statistik" id="total-analisis">0</div>
                <div class="catatan-statistik">Analisis tersimpan</div>
            </div>
            <div class="kartu-statistik">
                <div class="label-statistik">Hasil AI</div>
                <div class="nilai-statistik" id="analisis-ai">0</div>
                <div class="catatan-statistik">Diprediksi sebagai AI</div>
            </div>
            <div class="kartu-statistik">
                <div class="label-statistik">Rata-rata skor model</div>
                <div class="nilai-statistik" id="rata-rata-skor">0%</div>
                <div class="catatan-statistik">Kelas yang diprediksi</div>
            </div>
        </div>

        <div class="filter-riwayat no-print">
            <label class="pencarian-riwayat">@include('icon', ['name' => 'search'])<input id="pencarian-riwayat"
                    class="form-control" aria-label="Cari riwayat berdasarkan nama file" placeholder="Cari nama file..."></label>
            <select id="filter-riwayat" class="form-control" aria-label="Filter riwayat berdasarkan hasil">
                <option value="all">Semua status</option>
                <option value="ai">Prediksi AI-generated</option>
                <option value="human">Prediksi human</option>
                <option value="review">Belum selesai atau gagal</option>
            </select>
            <select id="urutan-riwayat" class="form-control" aria-label="Urutkan riwayat">
                <option value="newest">Terbaru</option>
                <option value="highest">Skor tertinggi</option>
                <option value="lowest">Skor terendah</option>
            </select>
        </div>

        <div
            class="daftar-riwayat"
            id="daftar-riwayat"
            data-analyses="{{ json_encode($analyses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
            data-delete-url="{{ route('history.destroy', ['analysisId' => '__ID__']) }}"
            data-delete-all-url="{{ route('history.destroy-all') }}"
            aria-live="polite"
        >
            <div class="kosong-riwayat">
                <p>Memuat riwayat analisis...</p>
            </div>
        </div>
    </div>
</section>

<div class="modal-detail-riwayat detail-modal" id="modal-detail-riwayat" hidden>
    <div class="panel-detail-riwayat">
        <div class="kepala-detail-riwayat">
            <div>
                <div class="label-detail-riwayat">Detail analisis</div>
                <h2 class="judul-detail-riwayat" id="judul-detail-riwayat">-</h2>
            </div><button type="button" class="auth-close" id="tombol-tutup-detail">@include('icon', ['name' =>
                'close'])</button>
        </div>

        {{-- Gambar yang dianalisis --}}
        <div class="gambar-detail-wrapper">
            <img id="gambar-detail-riwayat" class="gambar-detail-riwayat" alt="Gambar yang dianalisis" hidden>

            <p id="pesan-gambar-detail-riwayat" class="pesan-gambar-detail-riwayat" hidden>
                Pratinjau gambar tidak tersedia.
            </p>
        </div>

        <div class="skor-detail-riwayat">
            <div class="kotak-skor-detail">
                <div class="label-skor-detail" id="label-hasil-detail">Hasil klasifikasi</div>
                <div class="nilai-skor-detail" id="nilai-ai-detail">0%</div>
            </div>
            <div class="kotak-skor-detail netral">
                <div class="label-skor-detail">Skor kelas terpilih</div>
                <div class="nilai-skor-detail" style="color:#10243e" id="nilai-manusia-detail">0%</div>
            </div>
        </div>
        <div class="info-detail-riwayat">
            <div class="baris-info-detail"><span>Status</span><strong id="status-detail-riwayat">-</strong></div>
            <div class="baris-info-detail"><span>Waktu pemeriksaan</span><strong id="tanggal-detail-riwayat">-</strong>
            </div>
            <div class="baris-info-detail"><span>ID analisis</span><strong id="id-detail-riwayat">-</strong></div>
        </div>
        <div class="peringatan-detail-riwayat">@include('icon', ['name' => 'info', 'class' => 'icon-sm'])Hasil merupakan
            estimasi model dan sebaiknya digunakan bersama pemeriksaan sumber.</div>
        <button type="button" class="btn btn-primary tombol-simpan-laporan" id="tombol-simpan-laporan">
            @include('icon', ['name' => 'download', 'class' => 'icon-sm'])
            Simpan laporan
        </button>
    </div>
</div>
@endsection