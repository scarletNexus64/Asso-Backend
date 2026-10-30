{{-- Page affichée au retour de l'inscription vendeur (navigateur intégré de l'app).
     $variant : 'success' (inscription envoyée) ou 'expired' (lien expiré). --}}
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#FFF7F1" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#16120F" media="(prefers-color-scheme: dark)">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · ASSO</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <style>
        :root {
            --bg: #FFF7F1;
            --bg-glow: rgba(252, 145, 65, .18);
            --card: #FFFFFF;
            --text: #1F1A17;
            --muted: #6B625C;
            --line: #F1E6DE;
            --accent: #FC9141;
            --accent-strong: #E8762A;
            --accent-soft: #FFF0E4;
            --ok: #1F9D62;
            --ok-soft: #E6F6EE;
            --warn: #C77714;
            --warn-soft: #FFF3E0;
            --shadow: 0 1px 2px rgba(31, 26, 23, .04), 0 12px 32px rgba(31, 26, 23, .08);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #16120F;
                --bg-glow: rgba(252, 145, 65, .14);
                --card: #211B17;
                --text: #F6EFEA;
                --muted: #B3A79F;
                --line: #2F2722;
                --accent-soft: #3A2718;
                --ok: #4CC98D;
                --ok-soft: #173326;
                --warn: #F0A548;
                --warn-soft: #3A2A15;
                --shadow: 0 1px 2px rgba(0, 0, 0, .3), 0 12px 32px rgba(0, 0, 0, .35);
            }
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: max(24px, env(safe-area-inset-top)) 16px max(24px, env(safe-area-inset-bottom));
            background:
                radial-gradient(120% 60% at 50% -10%, var(--bg-glow), transparent 70%),
                var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            line-height: 1.5;
        }
        .card {
            width: 100%;
            max-width: 420px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 24px;
            box-shadow: var(--shadow);
            padding: 32px 24px 24px;
            text-align: center;
            animation: rise .45s cubic-bezier(.2, .8, .2, 1) both;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 28px;
            font-weight: 700;
            font-size: 15px;
            letter-spacing: .02em;
        }
        .brand img {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #fff;
            object-fit: contain;
            padding: 3px;
            box-shadow: 0 0 0 1px var(--line);
        }
        .status {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            animation: pop .5s .15s cubic-bezier(.2, .9, .3, 1.3) both;
        }
        .status svg { width: 34px; height: 34px; }
        .status.success { background: var(--ok-soft); color: var(--ok); }
        .status.expired { background: var(--warn-soft); color: var(--warn); }
        h1 {
            margin: 0 0 8px;
            font-size: 22px;
            line-height: 1.3;
            font-weight: 700;
            letter-spacing: -.01em;
        }
        .lead {
            margin: 0 auto;
            max-width: 34ch;
            color: var(--muted);
            font-size: 15.5px;
        }
        .steps {
            list-style: none;
            margin: 24px 0 0;
            padding: 16px;
            border-radius: 16px;
            background: var(--accent-soft);
            text-align: left;
            display: grid;
            gap: 12px;
        }
        .steps li { display: flex; gap: 12px; align-items: flex-start; font-size: 14.5px; }
        .steps .n {
            flex: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: var(--accent);
            color: #fff;
            font-size: 13px;
            font-weight: 700;
            display: grid;
            place-items: center;
            margin-top: 1px;
        }
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-height: 52px;
            margin-top: 24px;
            border: 0;
            border-radius: 14px;
            background: var(--accent);
            color: #fff;
            font: inherit;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s, transform .1s;
            -webkit-tap-highlight-color: transparent;
        }
        .btn:hover { background: var(--accent-strong); }
        .btn:active { transform: scale(.98); }
        .btn:focus-visible { outline: 3px solid var(--accent-soft); outline-offset: 2px; }
        .hint {
            margin: 12px 0 0;
            font-size: 13px;
            color: var(--muted);
        }
        .secure {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            text-align: left;
            margin-top: 20px;
            font-size: 12.5px;
            color: var(--muted);
        }
        .secure svg { flex: none; width: 14px; height: 14px; }
        @keyframes rise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
        @keyframes pop { from { opacity: 0; transform: scale(.6); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .card, .status { animation: none; } }
        @media (min-width: 480px) { .card { padding: 40px 36px 32px; } h1 { font-size: 24px; } }
    </style>
</head>
<body>
    <main class="card" role="main">
        <div class="brand">
            <img src="{{ asset('images/logo.png') }}" alt="" width="36" height="36">
            <span>ASSO</span>
        </div>

        <div class="status {{ $variant }}" aria-hidden="true">
            @if ($variant === 'success')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
            @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2"/></svg>
            @endif
        </div>

        <h1>{{ $title }}</h1>
        <p class="lead">{{ $body }}</p>

        @if (!empty($steps))
            <ol class="steps">
                @foreach ($steps as $i => $step)
                    <li><span class="n">{{ $i + 1 }}</span><span>{{ $step }}</span></li>
                @endforeach
            </ol>
        @endif

        <button type="button" class="btn" id="back">{{ $button }}</button>
        <p class="hint" id="hint" hidden>{{ $hint }}</p>

        <div class="secure">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
            <span>{{ $secure }}</span>
        </div>
    </main>

    <script>
        // Le navigateur intégré de l'app ne se ferme pas toujours par script :
        // on affiche alors l'indication pour le fermer à la main.
        document.getElementById('back').addEventListener('click', function () {
            window.close();
            setTimeout(function () { document.getElementById('hint').hidden = false; }, 300);
        });
    </script>
</body>
</html>
