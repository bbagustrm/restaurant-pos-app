<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    @include('partials.head')
    <style>
        body { font-size: 16px; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="min-h-screen bg-black text-slate-100 antialiased">
    {{ $slot }}
    @fluxScripts
</body>
</html>
