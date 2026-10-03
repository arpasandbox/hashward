<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Hashward')</title>
    <link rel="icon" href="{{ asset('images/favicon.png') }}" type="image/png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="sb-body">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:m-2 focus:rounded focus:bg-white focus:px-3 focus:py-2">Skip to content</a>
    @yield('content')
    @stack('scripts')
</body>
</html>
