<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Season builder · ThePiste' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    <header style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px clamp(16px,4vw,44px);background:var(--surface);border-bottom:1px solid var(--border);">
        <a href="{{ url('/') }}" style="font-family:'Space Mono',monospace;font-weight:700;letter-spacing:.14em;color:var(--ink);text-decoration:none;">THEPISTE</a>
        <nav style="display:flex;align-items:center;gap:14px;font-size:13.5px;">
            <a href="{{ route('calendar') }}" style="color:var(--ink-soft);text-decoration:none;">Calendar</a>
            @auth
                <span style="color:var(--muted);">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ url('/logout') }}">@csrf
                    <button class="btn btn-ghost" style="padding:6px 13px;font-size:13.5px;">Log out</button>
                </form>
            @endauth
        </nav>
    </header>

    {{ $slot }}

    @livewireScripts
</body>
</html>
