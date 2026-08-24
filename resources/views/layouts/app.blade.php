<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'imgk — Transforme une image juste en écrivant une adresse')</title>
    <meta name="description" content="@yield('description', 'Redimensionne, convertis, allège et habille n\'importe quelle image avec un simple lien. Zéro compte, gratuit, open source.')">

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    <meta property="og:title" content="@yield('og_title', 'imgk')">
    <meta property="og:description" content="Transforme une image juste en écrivant une adresse.">
    <meta property="og:type" content="website">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen antialiased">
@yield('body')
</body>
</html>
