<div class="profile-account-row">
    <dt>Sisa kuota 7 hari</dt>
    <dd>
        @if ($account['quota']['limit'] === null)
            Tanpa batas
        @else
            {{ number_format($account['quota']['remaining'], 0, ',', '.') }} dari {{ number_format($account['quota']['limit'], 0, ',', '.') }} analisis
        @endif
    </dd>
</div>
@if ($account['subscription']?->ends_at)
    <div class="profile-account-row">
        <dt>Aktif hingga</dt>
        <dd><time datetime="{{ $account['subscription']->ends_at->toIso8601String() }}">{{ $account['subscription']->ends_at->locale('id')->translatedFormat('j F Y') }}</time></dd>
    </div>
@endif
