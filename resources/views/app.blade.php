<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'TMS') }}</title>

    {{-- Tema ditetapkan SEBELUM paint agar tidak ada kedipan terang/gelap. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('tms.theme');
                if (t === 'dark' || (t !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>

    {{-- Albert Sans, mengikuti design system kpn-tmp / kpn-coi. --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=albert-sans:400,500,600,700&display=swap" rel="stylesheet">

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>

{{-- Tinggi tidak dikunci di sini: AppLayout memakai h-screen, dan halaman
     tamu harus tetap bisa digulir bila kartunya lebih tinggi dari layar. --}}
<body class="font-sans antialiased bg-surface-sunken text-ink-muted">
    @inertia
</body>

</html>
