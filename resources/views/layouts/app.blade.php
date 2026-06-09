<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'ThePiste' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,100..900&family=Martian+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ url('/') }}">THE<span>PISTE</span></a>
        <nav>
            @auth
                <span class="who">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ url('/logout') }}" style="margin:0">@csrf
                    <button class="bnav" type="submit">Log out</button>
                </form>
            @endauth
            @guest
                <a class="bnav" href="{{ url('/login') }}">Sign in</a>
            @endguest
        </nav>
    </header>
    @yield('content')
</body>
</html>
