@extends('layouts.app')

@section('title', 'A.I Check | Deteksi Poster')

@section('content')
    {{-- Bagian ini menampilkan judul dan state upload untuk memulai pemeriksaan gambar. --}}
    <section class="halaman-pemeriksaan">
        <div class="container">
            <div class="judul-halaman-pemeriksaan">
                <span class="eyebrow"><x-icon name="spark" class="icon-sm" />Analisis berbasis Hugging Face</span>
                <h1 class="judul-pemeriksaan">Periksa keaslian poster Anda</h1>
                <p class="deskripsi-pemeriksaan">Unggah poster untuk mendeteksi indikasi visual yang umum ditemukan pada gambar generatif AI.</p>
            </div>

            <div id="kotak-upload" class="kotak-upload">
                <div class="area-upload" id="area-drop-gambar">
                    <span class="ikon-upload"><x-icon name="upload" style="width:32px;height:32px" /></span>
                    <h2 class="judul-upload">Tarik &amp; lepas poster di sini</h2>
                    <p class="deskripsi-upload">atau pilih gambar dari perangkat Anda</p>
                    <button type="button" class="btn btn-light-blue tombol-utama" id="tombol-pilih-gambar">Pilih Gambar</button>
                    <p class="catatan-upload">JPG, PNG, atau WEBP • Maksimal 10 MB</p>
                    <input type="file" id="input-gambar" accept="image/png,image/jpeg,image/webp" hidden>
                </div>
            </div>

            {{-- Bagian hasil menampilkan pratinjau gambar dan ringkasan analisis yang sedang berjalan. --}}
            <div id="tampilan-hasil" class="tampilan-hasil" hidden>
                <div class="kartu-gambar">
                    <div class="area-pratinjau" id="area-pratinjau">
                        <img id="gambar-pratinjau" class="gambar-pratinjau" alt="Poster yang akan dianalisis">
                        <span class="label-pratinjau">Pratinjau poster</span>
                        <span class="titik-marker titik-marker-satu" hidden></span>
                        <span class="titik-marker titik-marker-dua" hidden></span>
                        <span class="titik-marker titik-marker-tiga" hidden></span>
                    </div>
                    <div class="meta-gambar">
                        <div class="ringkasan-file"><p class="nama-file" id="nama-file">-</p><p class="ukuran-file" id="ukuran-file">-</p></div>
                        <button type="button" class="icon-button tombol-hapus-gambar" id="tombol-hapus-gambar" title="Hapus gambar"><x-icon name="trash" /></button>
                    </div>
                </div>

                <div class="panel-hasil" id="panel-hasil">
                    <div id="area-siap-analisis">
                        <span class="ikon-panel"><x-icon name="layers" /></span>
                        <h2 class="judul-panel">Siap untuk dianalisis</h2>
                        <p class="deskripsi-panel">Model akan memeriksa tekstur, inkonsistensi bentuk, komposisi, dan pola piksel pada poster.</p>
                        <div class="daftar-analisis">
                            <div class="item-analisis"><span class="cek-analisis"><x-icon name="check" class="icon-sm" /></span>Analisis artefak visual</div>
                            <div class="item-analisis"><span class="cek-analisis"><x-icon name="check" class="icon-sm" /></span>Pemeriksaan pola generatif</div>
                            <div class="item-analisis"><span class="cek-analisis"><x-icon name="check" class="icon-sm" /></span>Skor probabilitas AI</div>
                        </div>
                        <div class="kemajuan-analisis" id="kemajuan-analisis" hidden>
                            <div class="kepala-kemajuan"><span>Menganalisis poster...</span><span id="nilai-kemajuan">0%</span></div>
                            <div class="lajur-progres"><div class="isi-progres" id="isi-kemajuan" style="width:0%"></div></div>
                        </div>
                        <button type="button" class="btn btn-primary btn-full tombol-analisis" id="tombol-analisis" style="margin-top:24px"><x-icon name="spark" />Mulai Analisis</button>
                    </div>

                    <div id="hasil-analisis" hidden></div>
                </div>
            </div>
        </div>
    </section>
@endsection
