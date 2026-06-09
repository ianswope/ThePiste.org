<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') · ThePiste</title>
    {{-- Self-contained on purpose: error pages must not depend on built assets. --}}
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center;
            font-family: 'Archivo', ui-sans-serif, system-ui, sans-serif;
            background: #F1F3F6; color: #131B29; padding: 24px;
        }
        .card {
            max-width: 460px; width: 100%; background: #fff; border: 1px solid #DFE4EA;
            border-radius: 16px; padding: 38px 34px; text-align: center;
            box-shadow: 0 10px 24px -12px rgba(19,27,41,.22); position: relative; overflow: hidden;
        }
        .card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, #E84B5C 0%, #E84B5C 38%, #D5DBE3 50%, #2DB95E 62%, #2DB95E 100%);
        }
        .brand { font-family: ui-monospace, monospace; font-weight: 700; letter-spacing: .22em; font-size: 13px; color: #66707F; }
        .code { font-family: ui-monospace, monospace; font-size: 56px; font-weight: 700; margin: 18px 0 4px; line-height: 1; }
        h1 { font-size: 21px; margin: 0 0 10px; letter-spacing: -.01em; font-weight: 800; text-transform: uppercase; }
        p { color: #66707F; font-size: 15px; line-height: 1.6; margin: 0 0 24px; }
        a.btn {
            display: inline-block; background: #168F42; color: #fff; text-decoration: none;
            font-weight: 700; font-size: 15px; padding: 12px 22px; border-radius: 10px;
        }
        .sword { font-size: 30px; opacity: .5; }
    </style>
</head>
<body>
    <main class="card">
        <div class="brand">THEPISTE</div>
        <div class="code">@yield('code')</div>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <a class="btn" href="{{ url('/') }}">Back to the piste</a>
    </main>
</body>
</html>
