@extends('layouts.admin')

@section('title', 'A.I Check | Detail Pengguna')

@section('content')
    <section class="admin-page" aria-labelledby="admin-user-detail-title">
        <div class="admin-user-detail-top">
            <a href="{{ route('admin.users.index') }}" class="admin-back-link">
                @include('icon', ['name' => 'arrow', 'class' => 'icon-sm'])
                Kembali ke pengguna
            </a>
            <span class="admin-badge {{ $user->role === \App\Models\User::ROLE_ADMIN ? 'is-admin' : '' }}">
                {{ $user->role === \App\Models\User::ROLE_ADMIN ? 'Admin' : 'Pengguna' }}
            </span>
        </div>

        <div class="admin-user-profile-heading">
            <span class="admin-user-profile-avatar" aria-hidden="true">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
            <div>
                <span class="eyebrow">@include('icon', ['name' => 'user', 'class' => 'icon-sm'])Detail akun</span>
                <h1 class="admin-title" id="admin-user-detail-title">{{ $user->name }}</h1>
                <p class="admin-description">{{ $user->email }}</p>
            </div>
        </div>

        <div class="admin-user-detail-grid">
            <section class="admin-data-panel" aria-labelledby="admin-user-account-title">
                <div class="admin-section-heading">
                    <div>
                        <h2 class="admin-section-title" id="admin-user-account-title">Informasi akun</h2>
                        <p class="admin-section-description">Data profil yang tersedia.</p>
                    </div>
                </div>
                <dl class="admin-user-info-list">
                    <div>
                        <dt>Nama</dt>
                        <dd>{{ $user->name }}</dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd>{{ $user->email }}</dd>
                    </div>
                    <div>
                        <dt>Role</dt>
                        <dd>{{ $user->role === \App\Models\User::ROLE_ADMIN ? 'Admin' : 'Pengguna' }}</dd>
                    </div>
                    <div>
                        <dt>Tanggal pendaftaran</dt>
                        <dd>{{ $user->created_at?->format('d/m/Y H:i') ?? 'Tidak tersedia' }}</dd>
                    </div>
                    <div>
                        <dt>Verifikasi email</dt>
                        <dd>{{ $user->email_verified_at ? 'Terverifikasi' : 'Belum terverifikasi' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="admin-data-panel" aria-labelledby="admin-user-analysis-summary-title">
                <div class="admin-section-heading">
                    <div>
                        <h2 class="admin-section-title" id="admin-user-analysis-summary-title">Ringkasan analisis</h2>
                        <p class="admin-section-description">Jumlah seluruh record dan lima analisis terbaru.</p>
                    </div>
                </div>
                <div class="admin-user-analysis-total">
                    <strong>{{ number_format($analysisCount) }}</strong>
                    <span>Total analisis</span>
                </div>
            </section>
        </div>

        <section class="admin-data-panel admin-user-subscriptions-panel" aria-labelledby="admin-user-subscriptions-title">
            <div class="admin-section-heading">
                <div>
                    <h2 class="admin-section-title" id="admin-user-subscriptions-title">Subscription</h2>
                    <p class="admin-section-description">Hingga 10 record subscription terbaru.</p>
                </div>
            </div>

            @if ($subscriptions->isNotEmpty())
                <div class="admin-users-table-scroll">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Paket</th>
                                <th scope="col">Status</th>
                                <th scope="col">Mulai</th>
                                <th scope="col">Berakhir</th>
                                <th scope="col">Dibatalkan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subscriptions as $subscription)
                                <tr>
                                    <td>{{ $subscription->plan?->name ?? 'Paket tidak tersedia' }}</td>
                                    <td>
                                        <span class="admin-badge {{ $subscription->status === 'active' ? 'is-premium' : '' }}">
                                            {{ match ($subscription->status) {
                                                'active' => 'Aktif',
                                                'pending' => 'Menunggu',
                                                'expired' => 'Kedaluwarsa',
                                                'cancelled' => 'Dibatalkan',
                                                default => 'Tidak diketahui',
                                            } }}
                                        </span>
                                    </td>
                                    <td>{{ $subscription->starts_at?->format('d/m/Y H:i') ?? 'Tidak tersedia' }}</td>
                                    <td>{{ $subscription->ends_at?->format('d/m/Y H:i') ?? 'Tanpa tanggal berakhir' }}</td>
                                    <td>{{ $subscription->cancelled_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="admin-empty-state">
                    <span class="admin-empty-icon">@include('icon', ['name' => 'refresh'])</span>
                    <p>Pengguna ini belum memiliki data subscription.</p>
                </div>
            @endif
        </section>

        <section class="admin-data-panel admin-user-analyses-panel" aria-labelledby="admin-user-analyses-title">
            <div class="admin-section-heading">
                <div>
                    <h2 class="admin-section-title" id="admin-user-analyses-title">Analisis terbaru</h2>
                    <p class="admin-section-description">Informasi metadata dan hasil yang aman; file dan gambar tidak ditampilkan.</p>
                </div>
            </div>

            @if ($recentAnalyses->isNotEmpty())
                <div class="admin-users-table-scroll">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Jenis file</th>
                                <th scope="col">Status</th>
                                <th scope="col">Hasil</th>
                                <th scope="col">Probabilitas AI</th>
                                <th scope="col">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentAnalyses as $analysis)
                                <tr>
                                    <td>#{{ $analysis->id }}</td>
                                    <td>{{ strtoupper(str_replace('image/', '', $analysis->mime_type)) }}</td>
                                    <td>{{ match ($analysis->status) {
                                        'pending' => 'Menunggu',
                                        'processing' => 'Diproses',
                                        'completed' => 'Selesai',
                                        'failed' => 'Gagal',
                                        default => 'Tidak diketahui',
                                    } }}</td>
                                    <td>{{ $analysis->result_label ?? 'Tidak tersedia' }}</td>
                                    <td>{{ $analysis->confidence_score === null
                                        ? 'Tidak tersedia'
                                        : number_format((float) $analysis->confidence_score * 100, 2, ',', '.').'%' }}</td>
                                    <td>{{ $analysis->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="admin-empty-state">
                    <span class="admin-empty-icon">@include('icon', ['name' => 'image'])</span>
                    <p>Pengguna ini belum memiliki riwayat analisis.</p>
                </div>
            @endif
        </section>
    </section>
@endsection
