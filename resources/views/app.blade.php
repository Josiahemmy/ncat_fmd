<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#101A62">
        <meta name="description" content="NCAT Flight Maintenance Department Inventory & Stores Management System.">

        <title inertia>{{ config('app.name', 'NCAT FMD Inventory') }}</title>

        {{-- Favicons / brand icons (self-hosted, no external calls) --}}
        <link rel="icon" href="/brand/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="/brand/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/brand/favicon-16x16.png">
        <link rel="apple-touch-icon" href="/brand/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">

        {{-- Fonts are self-hosted via @fontsource (imported in resources/css/app.css) --}}

        {{-- Scripts --}}
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-background font-sans text-foreground antialiased">
        @inertia
    </body>
</html>
