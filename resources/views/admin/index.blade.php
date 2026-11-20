@extends('admin.app')

@section('title', 'A.I Check | Dashboard Admin')

@section('content')
    <div class="container admin-content">
        <div class="admin-heading">
            <span class="eyebrow">@include('icon', ['name' => 'lock', 'class' => 'icon-sm']) Area administrator</span>
            <h1>Dashboard</h1>
            <p>Pantau akun, pengunjung, dan pertumbuhan website A.I-Check.</p>
        </div>

        <div class="admin-grid admin-grid-four">
            <article class="admin-card"><span class="admin-card-label">Total Pengunjung</span><strong>{{ number_format($totalVisitors) }}</strong><small>Pengunjung unik tercatat</small></article>
            <article class="admin-card"><span class="admin-card-label">Pengunjung Online</span><strong>{{ number_format($onlineVisitors) }}</strong><small>Aktivitas 5 menit terakhir</small></article>
            <article class="admin-card"><span class="admin-card-label">Pengunjung Hari Ini</span><strong>{{ number_format($todayVisitors) }}</strong><small>Sejak pukul 00:00</small></article>
            <article class="admin-card"><span class="admin-card-label">Total Akun</span><strong>{{ number_format($totalAccounts) }}</strong><small>{{ number_format($activeAccounts) }} akun aktif</small></article>
        </div>

        <div class="admin-grid admin-grid-three">
            <article class="admin-card"><span class="admin-card-label">Akun Premium</span><strong>{{ number_format($premiumAccounts) }}</strong></article>
            <article class="admin-card"><span class="admin-card-label">Akun Free</span><strong>{{ number_format($freeAccounts) }}</strong></article>
            <article class="admin-card"><span class="admin-card-label">Akun Dibanned</span><strong>{{ number_format($bannedAccounts) }}</strong></article>
        </div>

        <div class="admin-chart-grid">
            <section class="admin-chart-card">
                <h2>Perbandingan Akun</h2>
                @php
                    $accountTotal = max(1, $premiumAccounts + $freeAccounts);
                    $premiumAngle = round(($premiumAccounts / $accountTotal) * 360);
                @endphp
                <div class="admin-donut" style="--premium-angle: {{ $premiumAngle }}deg">
                    <div><strong>{{ $premiumAccounts + $freeAccounts }}</strong><small>Total akun</small></div>
                </div>
                <div class="chart-legend"><span><i class="premium"></i>Premium {{ $premiumAccounts }}</span><span><i class="free"></i>Free {{ $freeAccounts }}</span></div>
            </section>

            <section class="admin-chart-card">
                <h2>Status Akun</h2>
                @foreach($statusAccounts as $label => $value)
                    @php $maxStatus = max(1, max($statusAccounts)); @endphp
                    <div class="admin-bar-row"><span>{{ $label }}</span><div><i style="width: {{ ($value / $maxStatus) * 100 }}%"></i></div><strong>{{ $value }}</strong></div>
                @endforeach
            </section>
        </div>

        <div class="admin-chart-grid">
            <section class="admin-chart-card">
                <h2>Pendaftaran Pengguna 30 Hari</h2>
                @if($registrationTrend->isEmpty())
                    <p class="admin-empty-chart">Belum ada data pendaftaran pada periode ini.</p>
                @else
                    <div class="admin-line-chart">
                        @foreach($registrationTrend as $date => $value)
                            @php $height = max(8, ($value / max(1, $registrationTrend->max())) * 100); @endphp
                            <div title="{{ $date }}: {{ $value }}"><i style="height: {{ $height }}%"></i><small>{{ \Illuminate\Support\Carbon::parse($date)->format('d/m') }}</small></div>
                        @endforeach
                    </div>
                @endif
            </section>
            <section class="admin-chart-card">
                <h2>Pengunjung 30 Hari</h2>
                @if($visitorTrend->isEmpty())
                    <p class="admin-empty-chart">Belum ada data pengunjung pada periode ini.</p>
                @else
                    <div class="admin-line-chart visitor-chart">
                        @foreach($visitorTrend as $date => $value)
                            @php $height = max(8, ($value / max(1, $visitorTrend->max())) * 100); @endphp
                            <div title="{{ $date }}: {{ $value }}"><i style="height: {{ $height }}%"></i><small>{{ \Illuminate\Support\Carbon::parse($date)->format('d/m') }}</small></div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
