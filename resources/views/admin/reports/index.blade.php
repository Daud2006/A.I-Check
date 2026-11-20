@extends('layouts.admin')

@section('title', 'A.I Check | Laporan')

@section('content')
    <section class="admin-page admin-page-wide" aria-labelledby="admin-reports-title">
        <div class="admin-page-heading">
            <div>

                <h1 class="admin-title" id="admin-reports-title">Laporan</h1>

            </div>
        </div>

        @if ($errors->any())
            <div class="admin-analysis-validation" role="alert">
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="admin-data-panel admin-users-filter-panel" aria-label="Filter laporan">
            <form method="GET" action="{{ route('admin.reports.index') }}" class="admin-analysis-filters">
                <label class="admin-filter-field">
                    <span class="admin-filter-label">Dari tanggal</span>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="admin-filter-control">
                </label>
                <label class="admin-filter-field">
                    <span class="admin-filter-label">Sampai tanggal</span>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="admin-filter-control">
                </label>
                <label class="admin-filter-field">
                    <span class="admin-filter-label">Status analisis</span>
                    <select name="analysis_status" class="admin-filter-control">
                        <option value="">Semua status</option>
                        @foreach (\App\Models\ImageAnalysis::STATUSES as $status)
                            <option value="{{ $status }}" @selected($filters['analysis_status'] === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-filter-field">
                    <span class="admin-filter-label">Hasil klasifikasi</span>
                    <select name="result_label" class="admin-filter-control">
                        <option value="">Semua hasil</option>
                        @foreach (['AI-GENERATED', 'AI-EDITED', 'REAL', 'UNCERTAIN'] as $label)
                            <option value="{{ $label }}" @selected($filters['result_label'] === $label)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-filter-field">
                    <span class="admin-filter-label">Status pembayaran (CSV)</span>
                    <select name="payment_status" class="admin-filter-control">
                        <option value="">Semua status</option>
                        @foreach (\App\Models\Payment::STATUSES as $status)
                            <option value="{{ $status }}" @selected($filters['payment_status'] === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="admin-filter-field">
                    <span class="admin-filter-label">Paket transaksi/pengguna</span>
                    <select name="plan_id" class="admin-filter-control">
                        <option value="">Semua paket</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected($filters['plan_id'] === (string) $plan->id)>{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="admin-filter-actions">
                    <button type="submit" class="btn btn-primary">@include('icon', ['name' => 'search', 'class' => 'icon-sm'])Terapkan</button>
                    <a href="{{ route('admin.reports.index') }}" class="btn btn-admin-reset">Reset</a>
                </div>
            </form>
            <div class="admin-filter-actions admin-report-export-actions">
                <a href="{{ route('admin.reports.export', ['type' => 'users'] + array_intersect_key($filters, array_flip(['date_from', 'date_to', 'plan_id']))) }}" class="btn btn-secondary">Ekspor CSV pengguna</a>
                <a href="{{ route('admin.reports.export', ['type' => 'analyses'] + array_intersect_key($filters, array_flip(['date_from', 'date_to', 'analysis_status', 'result_label']))) }}" class="btn btn-secondary">Ekspor CSV analisis</a>
                <a href="{{ route('admin.reports.export', ['type' => 'transactions'] + array_intersect_key($filters, array_flip(['date_from', 'date_to', 'payment_status', 'plan_id']))) }}" class="btn btn-secondary">Ekspor CSV transaksi</a>
            </div>
        </section>

        <section class="admin-data-panel admin-report-section" aria-labelledby="admin-report-users-title">
            <div class="admin-section-heading">
                <div><h2 class="admin-section-title" id="admin-report-users-title">Laporan pengguna</h2></div>
            </div>
            <div class="admin-report-metrics">
                <article class="admin-report-metric"><span>Total akun</span><strong>{{ number_format($userCount) }}</strong></article>
                <article class="admin-report-metric"><span>Akun baru pada periode</span><strong>{{ number_format($newUserCount) }}</strong></article>
                <article class="admin-report-metric"><span>Online 5 menit terakhir</span><strong>{{ $onlineUsersAvailable ? number_format($onlineUsersCount) : 'Tidak tersedia' }}</strong></article>
            </div>
            <div class="admin-report-columns">
                <div>
                    <h3 class="admin-report-subtitle">Pengguna Premium aktif per paket</h3>
                    @forelse ($usersByPlan as $row)
                        <p>{{ $row->name }} <strong>{{ number_format($row->users_count) }}</strong></p>
                    @empty
                        <p>Belum ada subscription berbayar aktif.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="admin-data-panel admin-report-section" aria-labelledby="admin-report-analyses-title">
            <div class="admin-section-heading">
                <div><h2 class="admin-section-title" id="admin-report-analyses-title">Laporan analisis</h2></div>
            </div>
            <div class="admin-report-metrics">
                <article class="admin-report-metric"><span>Total analisis terfilter</span><strong>{{ number_format($analysisCount) }}</strong></article>
                <article class="admin-report-metric"><span>Label AI</span><strong>{{ number_format($aiAnalysisCount) }}</strong></article>
                <article class="admin-report-metric"><span>Label REAL</span><strong>{{ number_format($humanAnalysisCount) }}</strong></article>
                <article class="admin-report-metric"><span>UNCERTAIN / belum berlabel</span><strong>{{ number_format($unclassifiedAnalysisCount) }}</strong></article>
                <article class="admin-report-metric"><span>Penggunaan tercatat</span><strong>{{ number_format($recordedUsageCount) }}</strong></article>
            </div>
            <div class="admin-report-columns">
                <div>
                    <h3 class="admin-report-subtitle">Status analisis</h3>
                    @forelse ($analysesByStatus as $row)
                        <p>{{ ucfirst($row->status) }} <strong>{{ number_format($row->total) }}</strong></p>
                    @empty
                        <p>Belum ada analisis pada filter ini.</p>
                    @endforelse
                </div>
                <div>
                    <h3 class="admin-report-subtitle">Hasil klasifikasi</h3>
                    @forelse ($analysesByLabel as $row)
                        <p>{{ $row->result_label ?? 'Belum berlabel' }} <strong>{{ number_format($row->total) }}</strong></p>
                    @empty
                        <p>Belum ada hasil klasifikasi pada filter ini.</p>
                    @endforelse
                </div>
                <div>
                    <h3 class="admin-report-subtitle">Penggunaan tercatat per pengguna (10 teratas)</h3>
                    @forelse ($usageByUser as $row)
                        <p>{{ $row->name }} <span>{{ $row->email }}</span> <strong>{{ number_format($row->total_usage) }}</strong></p>
                    @empty
                        <p>Belum ada record penggunaan bertipe analysis. Data tidak disimpulkan dari jumlah analisis.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="admin-data-panel admin-report-section" aria-labelledby="admin-report-payments-title">
            <div class="admin-section-heading">
                <div><h2 class="admin-section-title" id="admin-report-payments-title">Laporan transaksi dan pendapatan</h2></div>
            </div>
            <div class="admin-report-columns">
                <div>
                    <h3 class="admin-report-subtitle">Transaksi berdasarkan status</h3>
                    @forelse ($paymentsByStatus as $row)
                        <p>{{ ucfirst($row->status) }} <strong>{{ number_format($row->total) }}</strong></p>
                    @empty
                        <p>Belum ada transaksi pada periode/paket ini.</p>
                    @endforelse
                </div>
                <div>
                    <h3 class="admin-report-subtitle">Pendapatan per mata uang</h3>
                    @forelse ($paidRevenueByCurrency as $row)
                        <p>{{ match ($row->environment) {'production' => 'Produksi', 'sandbox' => 'Sandbox', default => 'Lingkungan belum tercatat'} }}
                            <strong>{{ $row->currency }} {{ number_format((float) $row->total_amount, 2, ',', '.') }}</strong>
                        </p>
                    @empty
                        <p>Belum ada pembayaran berhasil pada periode/paket ini.</p>
                    @endforelse
                </div>
            </div>
            <div class="admin-report-table-wrap">
                <h3 class="admin-report-subtitle">Transaksi berdasarkan paket dan mata uang</h3>
                @if ($transactionsByPlan->isNotEmpty())
                    <table class="admin-table">
                        <thead><tr><th>Paket</th><th>Lingkungan</th><th>Mata uang</th><th>Transaksi</th><th>Pendapatan</th></tr></thead>
                        <tbody>
                            @foreach ($transactionsByPlan as $row)
                                <tr>
                                    <td>{{ $row->plan_name ?? 'Paket tidak tersedia' }}</td>
                                    <td>{{ match ($row->environment) {'production' => 'Produksi', 'sandbox' => 'Sandbox', default => 'Belum tercatat'} }}</td>
                                    <td>{{ $row->currency }}</td>
                                    <td>{{ number_format($row->transaction_count) }}</td>
                                    <td>{{ $row->currency }} {{ number_format((float) $row->paid_revenue, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p>Belum ada data transaksi untuk ditampilkan.</p>
                @endif
            </div>
        </section>
    </section>
@endsection
