@extends('app')

@section('title', 'A.I Check | Deteksi Poster')

@section('content')
    {{-- Unggah gambar untuk diperiksa. --}}
    <section class="halaman-pemeriksaan" aria-labelledby="judul-pemeriksaan">
        <div class="container">
            <div class="judul-halaman-pemeriksaan">
                <span class="eyebrow">@include('icon', ['name' => 'spark', 'class' => 'icon-sm'])AI Image Checker</span>
                <h1 class="judul-pemeriksaan" id="judul-pemeriksaan">Periksa gambar Anda</h1>
                <p class="deskripsi-pemeriksaan">Unggah poster dengan aman untuk mendapatkan prediksi model AI. Hasil bersifat probabilistik dan bukan bukti pasti.</p>
            </div>

            <p class="pesan-checker" id="pesan-checker" role="alert" aria-live="polite" hidden></p>

            <div id="kotak-upload" class="kotak-upload">
                <div class="area-upload" id="area-drop-gambar">
                    <span class="ikon-upload">@include('icon', ['name' => 'upload', 'style' => 'width:32px;height:32px'])</span>
                    <h2 class="judul-upload">Tarik &amp; lepas poster di sini</h2>
                    <p class="deskripsi-upload">atau pilih gambar dari perangkat Anda</p>
                    <button type="button" class="btn btn-light-blue tombol-utama" id="tombol-pilih-gambar">Pilih Gambar</button>
                    <p class="catatan-upload" id="petunjuk-format-gambar">JPEG, PNG, atau WebP · Maksimal 10 MB</p>
                    <input type="file" id="input-gambar" accept="image/png,image/jpeg,image/webp"
                        aria-label="Pilih gambar untuk dianalisis" aria-describedby="petunjuk-format-gambar"
                        data-upload-url="{{ route('checker.analyses.store') }}"
                        data-auth-url="{{ route('auth') }}" hidden>
                </div>
            </div>

            {{-- Pratinjau dan hasil pemeriksaan. --}}
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
                        <button type="button" class="icon-button tombol-hapus-gambar" id="tombol-hapus-gambar" title="Hapus gambar">@include('icon', ['name' => 'trash'])</button>
                    </div>
                </div>

                <div class="panel-hasil" id="panel-hasil">
                    <div id="area-siap-analisis">
                        <span class="ikon-panel">@include('icon', ['name' => 'layers'])</span>
                        <h2 class="judul-panel">Siap untuk diunggah</h2>
                        <p class="deskripsi-panel">Gambar divalidasi dan disimpan di storage privat aplikasi, lalu diproses oleh model klasifikasi lokal. Hasil menunjukkan skor kelas terpilih dan dapat keliru.</p>
                        <div class="daftar-analisis">
                            <div class="item-analisis"><span class="cek-analisis">@include('icon', ['name' => 'check', 'class' => 'icon-sm'])</span>Validasi gambar di server</div>
                            <div class="item-analisis"><span class="cek-analisis">@include('icon', ['name' => 'check', 'class' => 'icon-sm'])</span>Penyimpanan privat</div>
                            <div class="item-analisis"><span class="cek-analisis">@include('icon', ['name' => 'check', 'class' => 'icon-sm'])</span>Prediksi kelas artificial atau human</div>
                        </div>
                        <div class="kemajuan-analisis" id="kemajuan-analisis" role="status" aria-live="polite" aria-busy="false" hidden>
                            <div class="kepala-kemajuan"><span id="status-kemajuan">Mengunggah gambar...</span><span id="nilai-kemajuan">0%</span></div>
                            <div class="lajur-progres" role="progressbar" aria-label="Kemajuan unggah" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="isi-progres" id="isi-kemajuan" style="width:0%"></div></div>
                        </div>
                        <button type="button" class="btn btn-primary btn-full tombol-analisis" id="tombol-analisis" style="margin-top:24px">@include('icon', ['name' => 'upload'])Unggah Gambar</button>
                    </div>

                    <div id="hasil-analisis" role="status" aria-live="polite" hidden></div>
                </div>
            </div>
        </div>
    </section>
@endsection
