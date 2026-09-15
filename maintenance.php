<?php
/**
 * MoonAura Crystals — www subdomain maintenance page.
 * Standalone page intentionally avoids application/database dependencies.
 */
$siteUrl = 'https://moonauracrystals.in';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#5B2E91">

    <title>Under Maintenance | MoonAura Crystals</title>

    <link rel="icon" type="image/webp" href="/assets/images/icons/favicon/favicon.webp">
    <link rel="apple-touch-icon" href="/assets/images/icons/favicon/apple-touch-icon.webp">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #5B2E91;
            --primary-dark: #43206D;
            --gold: #D4AF37;
            --text: #222222;
            --text-light: #666666;
            --white: #ffffff;
            --bg: #faf8fc;
            --border: #ece7f5;
            --shadow: 0 18px 50px rgba(0, 0, 0, .10);
            --transition: .35s ease;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { min-height: 100%; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
            color: var(--text);
            background:
                radial-gradient(circle at 20% 15%, rgba(212, 175, 55, .10), transparent 34%),
                radial-gradient(circle at 85% 85%, rgba(91, 46, 145, .10), transparent 34%),
                var(--bg);
            font-family: 'Poppins', sans-serif;
            line-height: 1.7;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        a { color: inherit; text-decoration: none; }

        .maintenance-shell {
            width: min(100%, 820px);
        }

        .maintenance-card {
            position: relative;
            overflow: hidden;
            padding: clamp(36px, 7vw, 70px) clamp(24px, 7vw, 72px);
            text-align: center;
            background: rgba(255, 255, 255, .96);
            border: 1px solid var(--border);
            border-radius: 26px;
            box-shadow: var(--shadow);
        }

        .maintenance-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--gold), var(--primary));
        }

        .logo {
            width: min(100%, 360px);
            margin: 0 auto 32px;
        }

        .logo img {
            display: block;
            width: 80%;
            height: auto;
            margin: 0 auto;
        }

        .icon-wrap {
            width: 78px;
            height: 78px;
            margin: 0 auto 24px;
            display: grid;
            place-items: center;
            border: 2px solid rgba(212, 175, 55, .45);
            border-radius: 50%;
            background: #fbf8ef;
            color: var(--primary);
        }

        .icon-wrap svg {
            width: 34px;
            height: 34px;
        }

        .eyebrow {
            display: inline-block;
            margin-bottom: 12px;
            color: var(--gold);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: .16em;
            text-transform: uppercase;
        }

        h1 {
            max-width: 680px;
            margin: 0 auto;
            color: var(--primary);
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(42px, 7vw, 68px);
            line-height: 1.05;
            font-weight: 700;
        }

        .message {
            max-width: 610px;
            margin: 20px auto 0;
            color: var(--text-light);
            font-size: clamp(15px, 2vw, 18px);
        }

        .actions {
            display: flex;
            justify-content: center;
            margin-top: 32px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-width: 210px;
            min-height: 54px;
            padding: 0 28px;
            border-radius: 999px;
            background: var(--primary);
            color: var(--white);
            font-size: 15px;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: 0 10px 22px rgba(91, 46, 145, .16);
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 14px 28px rgba(91, 46, 145, .20);
        }

        .btn svg {
            width: 18px;
            height: 18px;
            transition: transform var(--transition);
        }

        .btn:hover svg { transform: translateX(3px); }

        .footer-note {
            margin-top: 24px;
            color: #8a8394;
            font-size: 12px;
        }

        @media (max-width: 640px) {
            body { padding: 18px 14px; }
            .maintenance-card { border-radius: 20px; }
            .logo { width: min(100%, 300px); margin-bottom: 26px; }
            .icon-wrap { width: 68px; height: 68px; margin-bottom: 20px; }
            .icon-wrap svg { width: 30px; height: 30px; }
            .actions { margin-top: 28px; }
            .btn { width: 100%; min-width: 0; }
        }
    </style>
</head>
<body>
    <main class="maintenance-shell">
        <section class="maintenance-card" aria-labelledby="maintenance-title">
            <div class="logo" aria-label="MoonAura Crystals home">
                <img src="/assets/images/icons/logos/logo-header.webp" alt="MoonAura Crystals">
            </div>

            <h1 id="maintenance-title">Website Under Maintenance</h1>

            <p class="message">
                We’re currently making a few updates to improve your experience.
                Our website will be back shortly.
            </p>

            <p class="footer-note">Thank you for your patience and understanding.</p>
        </section>
    </main>
</body>
</html>
