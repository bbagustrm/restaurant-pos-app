<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    @include('partials.head')
    <style>
        /* Hide scrollbar visually for the horizontal category tab strip while keeping it scrollable. */
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { scrollbar-width: none; -ms-overflow-style: none; }
    </style>
</head>
<body class="min-h-screen bg-slate-900 text-slate-100 antialiased">
    {{ $slot }}
    @fluxScripts
</body>
</html>
