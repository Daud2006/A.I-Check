
@extends('app')

@section('title', 'A.I Check | Riwayat')

@section('content')
{{-- Daftar riwayat pemeriksaan. --}}
<section class="halaman-riwayat">
    <div class="container">
        <div class="kepala-halaman-riwayat">
            <div>
                <span class="eyebrow">
                    @include('icon', ['name' => 'clock', 'class' => 'icon-sm'])
                    Aktivitas Anda
                </span>

                <h1 class="judul-halaman-riwayat">Riwayat analisis lengkap</h1>

                <p class="deskripsi-halaman-riwayat">
                    Cari, filter, dan tinjau kembali analisis yang tersimpan pada akun Anda.
                </p>
            </div>

            <button
                type="button"
                class="btn btn-danger-outline no-print"
                id="tombol-hapus-riwayat"
            >
                @include('icon', ['name' => 'trash', 'class' => 'icon-sm'])
                Hapus semua
            </button>
        </div>

        {{-- Statistik riwayat --}}
        <div class="kartu-statistik-riwayat">
            <div class="kartu-statistik">
                <div class="label-statistik">Total analisis</div>
                <div class="nilai-statistik" id="total-analisis">0</div>
                <div class="catatan-statistik">Analisis tersimpan</div>
            </div>

            <div class="kartu-statistik">
                <div class="label-statistik">Hasil AI</div>
                <div class="nilai-statistik" id="analisis-ai">0</div>
                <div class="catatan-statistik">AI-Generated dan AI-Edited</div>
            </div>

            <div class="kartu-statistik">
                <div class="label-statistik">Rata-rata probabilitas AI</div>
                <div class="nilai-statistik" id="rata-rata-skor">0%</div>
                <div class="catatan-statistik">Dari seluruh analisis selesai</div>
            </div>
        </div>

        {{-- Pencarian, filter, dan pengurutan --}}
        <div class="filter-riwayat no-print">
            <div class="pencarian-riwayat">
                <button
                    type="button"
                    class="tombol-pencarian-riwayat"
                    id="tombol-pencarian-riwayat"
                    aria-label="Fokus ke kolom pencarian riwayat"
                    aria-controls="pencarian-riwayat"
                >
                    @include('icon', ['name' => 'search'])
                </button>

                <input
                    type="search"
                    id="pencarian-riwayat"
                    class="form-control"
                    aria-label="Cari riwayat berdasarkan nama file, kategori, atau ID analisis"
                    placeholder="Cari nama file, kategori, atau ID..."
                >
            </div>

            <select
                id="filter-riwayat"
                class="form-control"
                aria-label="Filter riwayat berdasarkan hasil"
            >
                <option value="all">Semua hasil</option>
                <option value="ai">Semua hasil AI</option>
                <option value="ai-generated">AI-Generated</option>
                <option value="ai-edited">AI-Edited</option>
                <option value="real">Real (Gambar asli)</option>
                <option value="uncertain">Uncertain (Belum pasti)</option>
                <option value="review">Belum selesai atau gagal</option>
            </select>

            <select
                id="urutan-riwayat"
                class="form-control"
                aria-label="Urutkan riwayat"
            >
                <option value="newest">Terbaru</option>
                <option value="highest">Probabilitas AI tertinggi</option>
                <option value="lowest">Probabilitas AI terendah</option>
            </select>
        </div>

        {{-- Daftar riwayat analisis --}}
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

{{-- Modal detail riwayat --}}
<div class="modal-detail-riwayat detail-modal" id="modal-detail-riwayat" hidden>
    <div class="panel-detail-riwayat">
        <div class="kepala-detail-riwayat">
            <div>
                <div class="label-detail-riwayat">Detail analisis</div>
                <h2 class="judul-detail-riwayat" id="judul-detail-riwayat">-</h2>
            </div>

            <button
                type="button"
                class="auth-close"
                id="tombol-tutup-detail"
            >
                @include('icon', ['name' => 'close'])
            </button>
        </div>

        {{-- Gambar yang dianalisis --}}
        <div class="gambar-detail-wrapper">
            <img
                id="gambar-detail-riwayat"
                class="gambar-detail-riwayat"
                alt="Gambar yang dianalisis"
                hidden
            >

            <p
                id="pesan-gambar-detail-riwayat"
                class="pesan-gambar-detail-riwayat"
                hidden
            >
                Pratinjau gambar tidak tersedia.
            </p>
        </div>

        {{-- Hasil dan probabilitas --}}
        <div class="skor-detail-riwayat">
            <div class="kotak-skor-detail">
                <div class="label-skor-detail" id="label-hasil-detail">
                    Hasil Deteksi
                </div>

                <div class="nilai-skor-detail" id="nilai-ai-detail">-</div>
            </div>

            <div class="kotak-skor-detail netral">
                <div class="label-skor-detail">Probabilitas AI</div>

                <div
                    class="nilai-skor-detail"
                    style="color:#10243e"
                    id="nilai-manusia-detail"
                >
                    0%
                </div>
            </div>
        </div>

        {{-- Informasi analisis --}}
        <div class="info-detail-riwayat">
            <div class="baris-info-detail">
                <span>Status</span>
                <strong id="status-detail-riwayat">-</strong>
            </div>

            <div class="baris-info-detail">
                <span>Waktu pemeriksaan</span>
                <strong id="tanggal-detail-riwayat">-</strong>
            </div>

            <div class="baris-info-detail">
                <span>ID analisis</span>
                <strong id="id-detail-riwayat">-</strong>
            </div>
        </div>

        <section
            class="bagian-keterangan-analisis hasil-analisis-riwayat"
            id="hasil-analisis-riwayat"
            hidden
        >
            <h4>Hasil Analisis</h4>
            <p id="teks-hasil-analisis-riwayat"></p>
        </section>

        <button
            type="button"
            class="btn btn-primary tombol-simpan-laporan"
            id="tombol-simpan-laporan"
        >
            @include('icon', ['name' => 'download', 'class' => 'icon-sm'])
            Simpan laporan
        </button>
    </div>
</div>
@endsection
