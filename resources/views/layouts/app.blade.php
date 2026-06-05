<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Camino Unidos') }} | Subir documentos</title>
    <link rel="icon" type="image/png" href="{{ asset('company-logo-transparent.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('company-logo-transparent.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/upload-documents.css') }}?v={{ filemtime(public_path('assets/css/upload-documents.css')) }}">
    @livewireStyles
</head>
<body>
    {{ $slot ?? '' }}
    @yield('content')

    @livewireScripts
    <script src="{{ asset('assets/js/upload-documents.js') }}?v={{ filemtime(public_path('assets/js/upload-documents.js')) }}" defer></script>
</body>
</html>
