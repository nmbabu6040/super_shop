@props(['title' => null])
@php
    $settingsService = app(\App\Services\Settings\SettingsService::class);
    $primaryColor = (string) $settingsService->get('appearance', 'primary_color', '');
    // Only a strictly validated hex colour is ever turned into CSS.
    $brandShades = \App\Support\Branding\BrandPalette::isValidHex($primaryColor)
        ? \App\Support\Branding\BrandPalette::shades($primaryColor)
        : [];
    $faviconPath = $settingsService->get('appearance', 'favicon_path');
    $defaultTheme = (string) $settingsService->get('appearance', 'default_theme', 'system');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' | ' : '' }}{{ config('app.name') }}</title>

    @if ($faviconPath)
        <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($faviconPath) }}">
    @endif

    {{-- Apply the saved (or default) theme before first paint to avoid a light/dark flash. --}}
    <script>
        (function() {
            try {
                var defaultTheme = @json($defaultTheme);
                var storedTheme = localStorage.getItem('theme');
                var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                var useDark = storedTheme === 'dark' ||
                    (!storedTheme && (defaultTheme === 'dark' || (defaultTheme === 'system' && prefersDark)));
                if (useDark) {
                    document.documentElement.classList.add('dark');
                }
            } catch (storageError) {}
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if ($brandShades !== [])
        <style>
            :root {
                @foreach ($brandShades as $shade => $shadeHex)
                    --brand-{{ $shade }}: {{ $shadeHex }};
                @endforeach
            }
        </style>
    @endif
</head>

<body class="min-h-full bg-surface font-sans text-ink antialiased">
    {{ $slot }}
</body>

</html>
