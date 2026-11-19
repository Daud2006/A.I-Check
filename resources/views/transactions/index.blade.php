@extends('app')

@section('title', 'Riwayat Transaksi')

@section('content')
    <main class="billing-page">
        <div class="billing-page-inner">
            <header class="billing-heading">
                <span class="eyebrow">Pembayaran</span>
                <h1>Riwayat transaksi</h1>
                <p>Status berubah setelah notifikasi Midtrans diverifikasi oleh server.</p>
            </header>

            <section class="billing-panel" aria-label="Daftar transaksi">
                @if ($payments->isNotEmpty())
                    <div class="billing-table-scroll">
                        <table class="billing-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Paket</th>
                                    <th>Jumlah</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th><span class="billing-visually-hidden">Aksi</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($payments as $payment)
                                    <tr>
                                        <td>#{{ $payment->id }}</td>
                                        <td>{{ $payment->subscription?->plan?->name ?? 'Paket tidak tersedia' }}</td>
                                        <td>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2, ',', '.') }}</td>
                                        <td>
                                            <span class="billing-status is-{{ $payment->status }}">
                                                {{ match ($payment->status) {
                                                    'pending' => 'Menunggu pembayaran',
                                                    'paid' => 'Berhasil',
                                                    'failed' => 'Gagal',
                                                    'refunded' => 'Dikembalikan',
                                                    default => 'Tidak diketahui',
                                                } }}
                                            </span>
                                        </td>
                                        <td>{{ $payment->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                        <td><a href="{{ route('transactions.show', $payment->id) }}" class="billing-link">Detail</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($payments->hasPages())
                        <nav class="billing-pagination" aria-label="Halaman transaksi">
                            <span>Menampilkan {{ $payments->firstItem() }}–{{ $payments->lastItem() }} dari {{ $payments->total() }}</span>
                            {{ $payments->links() }}
                        </nav>
                    @endif
                @else
                    <div class="billing-empty" role="status">
                        <h2>Belum ada transaksi</h2>
                        <p>Transaksi paket yang Anda buat akan muncul di sini.</p>
                        <a href="{{ route('plans.index') }}" class="btn btn-primary">Lihat paket</a>
                    </div>
                @endif
            </section>
        </div>
    </main>
@endsection
