@extends('layouts.admin')

@section('title', 'A.I Check | Admin')

@section('content')
    <section class="admin-page admin-page-dashboard" aria-labelledby="admin-page-title">
        <div class="admin-page-heading">
            <div>

                <h1 class="admin-title" id="admin-page-title">Dashboard</h1>

            </div>
        </div>

        @if (! $dashboardError && ! $detectorReady)
            <p class="admin-status-note" role="status">AI Engine belum siap.</p>
        @endif

        @if ($dashboardError)
            <div class="admin-dashboard-error" role="alert">
                @include('icon', ['name' => 'info'])
                <p>{{ $dashboardError }}</p>
            </div>
        @else
            <div class="admin-stat-grid" aria-label="Ringkasan statistik">
                <article class="admin-stat-card">
                    <span class="admin-stat-icon">@include('icon', ['name' => 'user'])</span>
                    <span class="admin-stat-label">Total pengguna</span>
                    <strong class="admin-stat-value">{{ number_format($userCount) }}</strong>

                </article>

                <article class="admin-stat-card">
                    <span class="admin-stat-icon">@include('icon', ['name' => 'layers'])</span>
                    <span class="admin-stat-label">Total analisis</span>
                    <strong class="admin-stat-value">{{ number_format($analysisCount) }}</strong>

                </article>

                <article class="admin-stat-card">
                    <span class="admin-stat-icon">@include('icon', ['name' => 'clock'])</span>
                    <span class="admin-stat-label">Analisis hari ini</span>
                    <strong class="admin-stat-value">{{ number_format($todayAnalysisCount) }}</strong>
                    <span class="admin-stat-note">{{ number_format($todayOutcomes->get('completed', 0)) }} selesai · {{ number_format($todayOutcomes->get('failed', 0)) }} gagal</span>
                </article>

                <article class="admin-stat-card">
                    <span class="admin-stat-icon">@include('icon', ['name' => 'eye'])</span>
                    <span class="admin-stat-label">Pengguna online</span>
                    @if ($onlineUsersAvailable)
                        <strong class="admin-stat-value">{{ number_format($onlineUsersCount) }}</strong>
                        <span class="admin-stat-note">Aktif dalam 5 menit terakhir</span>
                    @else
                        <strong class="admin-stat-value admin-stat-unavailable">Tidak tersedia</strong>

                    @endif
                </article>

                <article class="admin-stat-card">
                    <span class="admin-stat-icon">@include('icon', ['name' => 'refresh'])</span>
                    <span class="admin-stat-label">Pengguna Premium aktif</span>
                    <strong class="admin-stat-value">{{ number_format($activeSubscriptionUsersCount) }}</strong>
                    <span class="admin-stat-note">{{ number_format($freeUserCount) }} pengguna Free</span>
                </article>

                <article class="admin-stat-card">
                    <span class="admin-stat-icon">@include('icon', ['name' => 'check'])</span>
                    <span class="admin-stat-label">Transaksi berhasil</span>
                    <strong class="admin-stat-value">{{ number_format($successfulPaymentCount) }}</strong>

                </article>

                <article class="admin-stat-card">
                    <span class="admin-stat-icon is-pending">@include('icon', ['name' => 'clock'])</span>
                    <span class="admin-stat-label">Transaksi menunggu</span>
                    <strong class="admin-stat-value">{{ number_format($pendingPaymentCount) }}</strong>

                </article>

                <article class="admin-stat-card admin-revenue-card">
                    <span class="admin-stat-icon">@include('icon', ['name' => 'check'])</span>
                    <span class="admin-stat-label">Pendapatan sepanjang waktu</span>
                    @forelse ($paidRevenueByCurrency as $revenue)
                        <strong class="admin-revenue-value">
                            {{ $revenue['currency'] }} {{ number_format((float) $revenue['amount'], 2, ',', '.') }}
                        </strong>
                    @empty
                        <strong class="admin-stat-value">0</strong>

                    @endforelse

                </article>
            </div>

            <section class="admin-data-panel" aria-labelledby="judul-pendapatan-bulan">
                <div class="admin-section-heading">
                    <h2 class="admin-section-title" id="judul-pendapatan-bulan">Pendapatan bulan ini</h2>
                </div>
                <dl class="admin-user-info-list">
                    @forelse ($monthlyRevenue as $revenue)
                        <div>
                            <dt>{{ match ($revenue->environment) {'production' => 'Pembayaran produksi', 'sandbox' => 'Uji Sandbox', default => 'Lingkungan belum tercatat'} }}</dt>
                            <dd>{{ $revenue->currency }} {{ number_format((float) $revenue->total, 2, ',', '.') }}</dd>
                        </div>
                    @empty
                        <div><dt>Pembayaran berhasil</dt><dd>Belum ada bulan ini</dd></div>
                    @endforelse
                </dl>
            </section>

            <section class="admin-data-panel admin-chart-panel" aria-labelledby="admin-analysis-chart-title">
                <div class="admin-section-heading">
                    <div>
                        <h2 class="admin-section-title" id="admin-analysis-chart-title">Aktivitas analisis</h2>

                    </div>
                    <span class="admin-period-badge">7 hari</span>
                </div>

                @if (collect($analysisChart)->sum('total') > 0)
                    @php
                        $maxAnalysisCount = max(collect($analysisChart)->max('total'), 1);
                    @endphp
                    <div class="admin-chart" role="list" aria-label="Jumlah analisis harian selama 7 hari terakhir">
                        @foreach ($analysisChart as $day)
                            @php
                                $barHeight = $day['total'] > 0
                                    ? max(8, (int) round(($day['total'] / $maxAnalysisCount) * 100))
                                    : 3;
                            @endphp
                            <div class="admin-chart-column" role="listitem" aria-label="{{ $day['date'] }}: {{ $day['total'] }} analisis">
                                <span class="admin-chart-value">{{ $day['total'] }}</span>
                                <div class="admin-chart-track">
                                    <span class="admin-chart-bar" style="height: {{ $barHeight }}%"></span>
                                </div>
                                <span class="admin-chart-label">{{ $day['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="admin-empty-state">
                        <span class="admin-empty-icon">@include('icon', ['name' => 'layers'])</span>
                        <p>Belum ada analisis dalam 7 hari terakhir.</p>
                    </div>
                @endif
            </section>

            <div class="admin-recent-grid">
                <section class="admin-data-panel" aria-labelledby="admin-recent-users-title">
                    <div class="admin-section-heading">
                        <div>
                            <h2 class="admin-section-title" id="admin-recent-users-title">Pengguna terbaru</h2>

                        </div>
                    </div>

                    @if ($recentUsers->isNotEmpty())
                        <div class="admin-table-scroll">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Pengguna</th>
                                        <th scope="col">Terdaftar</th>
                                        <th scope="col">Langganan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentUsers as $recentUser)
                                        <tr>
                                            <td>
                                                <span class="admin-table-primary">{{ $recentUser->name }}</span>
                                                <span class="admin-table-secondary">{{ $recentUser->email }}</span>
                                            </td>
                                            <td>{{ $recentUser->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                            <td>
                                                @if ($recentUser->has_active_subscription)
                                                    <span class="admin-badge is-premium">Premium aktif</span>
                                                @else
                                                    <span class="admin-badge">Free</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="admin-empty-state">
                            <span class="admin-empty-icon">@include('icon', ['name' => 'user'])</span>
                            <p>Belum ada pengguna terdaftar.</p>
                        </div>
                    @endif
                </section>

                <section class="admin-data-panel" aria-labelledby="admin-recent-analyses-title">
                    <div class="admin-section-heading">
                        <div>
                            <h2 class="admin-section-title" id="admin-recent-analyses-title">Analisis terbaru</h2>

                        </div>
                    </div>

                    @if ($recentAnalyses->isNotEmpty())
                        <div class="admin-table-scroll">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Pengguna</th>
                                        <th scope="col">File</th>
                                        <th scope="col">Hasil / status</th>
                                        <th scope="col">Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentAnalyses as $analysis)
                                        @php
                                            $analysisStatus = $analysis->status === 'completed'
                                                ? ($analysis->result_label ?: 'Selesai')
                                                : match ($analysis->status) {
                                                    'pending' => 'Menunggu',
                                                    'processing' => 'Diproses',
                                                    'failed' => 'Gagal',
                                                    default => 'Tidak diketahui',
                                                };
                                        @endphp
                                        <tr>
                                            <td><span class="admin-table-primary">{{ $analysis->user?->name ?? 'Pengguna tidak tersedia' }}</span></td>
                                            <td>{{ strtoupper(str_replace('image/', '', $analysis->mime_type)) }}</td>
                                            <td><span class="admin-badge">{{ $analysisStatus }}</span></td>
                                            <td>{{ $analysis->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="admin-empty-state">
                            <span class="admin-empty-icon">@include('icon', ['name' => 'image'])</span>
                            <p>Belum ada analisis gambar.</p>
                        </div>
                    @endif
                </section>
            </div>
        @endif
    </section>
@endsection
