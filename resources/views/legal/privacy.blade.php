@extends('app')

@section('title', 'Kebijakan Privasi | A.I-Check')

@section('content')
    <article class="about-page">
        <div class="page-container">
            <div class="about-note dokumen-situs">
                <h1>Kebijakan Privasi</h1>
                <section>
                    <h2>Akun dan sesi</h2>
                    <p>Aplikasi menyimpan nama, email, dan kata sandi dalam bentuk hash untuk mengelola akun. Cookie sesi membantu Anda tetap masuk. Anda bisa mengubah nama di Profil.</p>
                </section>
                <section>
                    <h2>Gambar dan hasil</h2>
                    <p>Gambar disimpan pada penyimpanan privat dan dianalisis oleh model lokal. Aplikasi menyimpan informasi file dan hasil agar Anda bisa melihatnya di Riwayat. Pemilik akun dapat membuka gambar melalui akun yang terkait, dan admin berwenang dapat melihat informasi analisis untuk pengelolaan layanan.</p>
                </section>
                <section>
                    <h2>Pembayaran</h2>
                    <p>Saat checkout, aplikasi mengirim nama, email, serta informasi pesanan ke Midtrans untuk memproses pembayaran. Anda memasukkan informasi pembayaran di halaman Midtrans. A.I-Check menyimpan informasi transaksi dan status pembayaran untuk mengelola langganan.</p>
                </section>
                <section>
                    <h2>Menghapus riwayat</h2>
                    <p>Anda dapat menghapus satu atau semua analisis dari Riwayat. Aplikasi mencoba membersihkan file yang sudah tidak dirujuk dan memberi tahu Anda jika pembersihan gagal. Catatan kuota dan transaksi disimpan terpisah dari Riwayat.</p>
                </section>
            </div>
        </div>
    </article>
@endsection
