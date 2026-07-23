<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#101A62">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('code') · @yield('title') · NCAT FMD</title>
    <link rel="icon" href="/brand/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/brand/favicon-32x32.png">
    <style>
        :root {
            --ncat-navy: #101A62;
            --ncat-blue: #009DE0;
            --ncat-sky: #13B8F0;
            --ncat-cyan: #00C2FF;
            --ncat-gold: #FFD600;
            --ncat-midnight: #050A23;
            --ncat-mist: #F3F7FB;
        }

        *,
        *::before,
        *::after { box-sizing: border-box; }

        html { -webkit-text-size-adjust: 100%; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #E8ECF6;
            background-color: #050A23;
            background-image:
                radial-gradient(1100px 620px at 78% -8%, rgba(0, 194, 255, 0.22), transparent 60%),
                radial-gradient(900px 560px at 12% 108%, rgba(0, 157, 224, 0.20), transparent 62%),
                linear-gradient(135deg, #050A23 0%, #101A62 55%, #0B2E6B 100%);
            background-attachment: fixed;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            line-height: 1.6;
        }

        /* Subtle grid texture overlay */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.035) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(circle at 50% 40%, #000 0%, transparent 78%);
            -webkit-mask-image: radial-gradient(circle at 50% 40%, #000 0%, transparent 78%);
        }

        .card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 560px;
            padding: clamp(2rem, 5vw, 3.25rem);
            text-align: center;
            background: rgba(10, 18, 52, 0.62);
            border: 1px solid rgba(0, 194, 255, 0.18);
            border-radius: 24px;
            box-shadow:
                0 24px 60px -20px rgba(5, 10, 35, 0.55),
                0 8px 24px -12px rgba(16, 26, 98, 0.45),
                inset 0 1px 0 rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
        }

        .crest {
            display: block;
            height: 64px;
            width: auto;
            margin: 0 auto 1.75rem;
            filter: drop-shadow(0 6px 18px rgba(0, 194, 255, 0.35));
        }

        .code {
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-weight: 800;
            font-size: clamp(4.5rem, 16vw, 7rem);
            line-height: 0.95;
            letter-spacing: -0.03em;
            margin: 0;
            background: linear-gradient(120deg, #009DE0 0%, #13B8F0 45%, #00C2FF 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            -webkit-text-fill-color: transparent;
        }

        .eyebrow {
            display: inline-block;
            margin-top: 0.75rem;
            padding: 0.3rem 0.85rem;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #9FE3FF;
            background: rgba(0, 194, 255, 0.10);
            border: 1px solid rgba(0, 194, 255, 0.24);
            border-radius: 999px;
        }

        h1 {
            font-family: 'Plus Jakarta Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-weight: 700;
            font-size: clamp(1.5rem, 4.5vw, 2rem);
            letter-spacing: -0.01em;
            margin: 1.25rem 0 0.65rem;
            color: #FFFFFF;
        }

        .message {
            margin: 0 auto;
            max-width: 42ch;
            font-size: 1rem;
            color: rgba(224, 232, 248, 0.82);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0.7rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 12px;
            border: 1px solid transparent;
            transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease, border-color 0.2s ease;
        }

        .btn:focus-visible {
            outline: 3px solid rgba(0, 194, 255, 0.7);
            outline-offset: 3px;
        }

        .btn-primary {
            color: #041028;
            background: linear-gradient(120deg, #009DE0 0%, #13B8F0 50%, #00C2FF 100%);
            box-shadow: 0 8px 28px -8px rgba(0, 194, 255, 0.55);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 34px -8px rgba(0, 194, 255, 0.7);
        }

        .btn-ghost {
            color: #E8ECF6;
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.18);
        }

        .btn-ghost:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.10);
            border-color: rgba(0, 194, 255, 0.4);
        }

        .footer {
            margin-top: 2rem;
            font-size: 0.8rem;
            letter-spacing: 0.02em;
            color: rgba(159, 227, 255, 0.6);
        }

        @media (max-width: 380px) {
            .btn { width: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            .btn { transition: none; }
            .btn:hover { transform: none; }
        }
    </style>
</head>
<body>
    <main class="card" role="main">
        <img class="crest" src="/brand/ncat-logo.png" alt="Nigerian College of Aviation Technology crest">

        <p class="code" aria-hidden="true">@yield('code')</p>
        <span class="eyebrow">@yield('eyebrow', 'NCAT FMD')</span>

        <h1>@yield('title')</h1>
        <p class="message">@yield('message')</p>

        <nav class="actions" aria-label="Recovery options">
            <a class="btn btn-primary" href="/dashboard">Back to dashboard</a>
            <a class="btn btn-ghost" href="/login">Sign in</a>
        </nav>

        <p class="footer">NCAT · Flight Maintenance Department · Inventory &amp; Stores</p>
    </main>
</body>
</html>
