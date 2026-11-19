@extends('app')

@section('title', 'Syarat Penggunaan | A.I-Check')

@section('content')
    <article class="about-page">
        <div class="page-container">
            <div class="about-note dokumen-situs">
                <h1>Syarat Penggunaan</h1>
                <section>
                    <h2>Akun dan unggahan</h2>
                    <p>Gunakan akun Anda sendiri dan jaga kerahasiaan kata sandi. Unggah gambar yang Anda miliki atau yang boleh Anda gunakan. Hindari unggahan yang melanggar privasi atau hak orang lain.</p>
                </section>
                <section>
                    <h2>Hasil Deteksi</h2>
                    <p>A.I-Check memperkirakan apakah gambar dibuat atau diedit dengan AI. Model dapat keliru. Tinjau sumber dan konteks gambar sebelum mengandalkan hasil untuk keputusan penting.</p>
                </section>
                <section>
                    <h2>Kuota dan pembayaran</h2>
                    <p>Kuota, harga, dan periode tersedia di halaman <a class="tautan-dokumen" href="{{ route('plans.index') }}">Langganan</a>. Anda membayar sekali untuk setiap periode yang dipilih. Paket Premium mulai berlaku setelah pembayaran diverifikasi, sesuai periode yang tercantum di detail transaksi.</p>
                </section>
                <section>
                    <h2>Data Anda</h2>
                    <p>Baca <a class="tautan-dokumen" href="{{ route('legal.privacy') }}">Kebijakan Privasi</a> untuk mengetahui cara aplikasi menggunakan data akun, gambar, dan pembayaran.</p>
                </section>
            </div>
        </div>
    </article>
@endsection
