{{-- Shared SVG icon component used by the Blade pages. --}}
@php
    $paths = [
        'upload' => '<path d="M12 16V4m0 0L7 9m5-5 5 5"/><path d="M4 15v4a1 1 0 001 1h14a1 1 0 001-1v-4"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5m0-9h.01"/>',
        'home' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/>',
        'spark' => '<path d="M12 3l1.1 3.4a5 5 0 003.2 3.2L20 11l-3.7 1.4a5 5 0 00-3.2 3.2L12 19l-1.1-3.4a5 5 0 00-3.2-3.2L4 11l3.7-1.4a5 5 0 003.2-3.2L12 3z"/>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3m3 0l-1 14H7L6 7M10 11v6m4-6v6"/>',
        'download' => '<path d="M12 3v12m0 0l-4-4m4 4 4-4"/><path d="M4 20h16"/>',
        'refresh' => '<path d="M20 7v5h-5"/><path d="M18.5 16a8 8 0 10.5-9l1 5"/>',
        'check' => '<path d="M5 12l4 4L19 6"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'arrow' => '<path d="M5 12h14m-5-5l5 5-5 5"/>',
        'lock' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/>',
        'layers' => '<path d="M12 2l9 5-9 5-9-5 9-5z"/><path d="M3 12l9 5 9-5M3 17l9 5 9-5"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'eye' => '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="2.5"/>',
        'logout' => '<path d="M10 4H5a2 2 0 00-2 2v12a2 2 0 002 2h5"/><path d="M14 16l4-4-4-4m4 4H8"/>',
    ];
@endphp
<svg aria-hidden="true" class="icon {{ $class ?? '' }}" viewBox="0 0 24 24"><g>{!! $paths[$name] ?? $paths['spark'] !!}</g></svg>
