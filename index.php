<?php
/**
 * Public landing page – directs visitors to vote for their champions
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Murang'a 40 Under 40 Awards – Vote for Your Champions</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --vote-bg: #1a1d24;
            --vote-surface: #23262e;
            --vote-border: #3a3f4b;
            --vote-text: #e8eaed;
            --vote-muted: #9aa0a8;
            --vote-accent: #22c4b8;
            --vote-accent-hover: #2dd9cc;
            --vote-shadow: 0 8px 32px rgba(0,0,0,0.35);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            margin: 0;
            min-height: 100vh;
            background: var(--vote-bg);
            color: var(--vote-text);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .landing-wrap {
            max-width: 520px;
            width: 100%;
            text-align: center;
        }
        .landing-hero {
            background: var(--vote-surface);
            border: 1px solid var(--vote-border);
            border-radius: 1.25rem;
            box-shadow: var(--vote-shadow);
            padding: 2.5rem 2rem;
            margin-bottom: 1.5rem;
        }
        .landing-hero .icon-wrap {
            width: 4rem;
            height: 4rem;
            margin: 0 auto 1.25rem;
            background: linear-gradient(135deg, var(--vote-accent), #1a9d94);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .landing-hero .icon-wrap i {
            font-size: 2rem;
            color: var(--vote-bg);
        }
        .landing-hero h1 {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin: 0 0 0.5rem;
            color: var(--vote-text);
        }
        .landing-hero p {
            color: var(--vote-muted);
            font-size: 1rem;
            line-height: 1.5;
            margin: 0 0 1.75rem;
        }
        .landing-cta {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 1.75rem;
            background: var(--vote-accent);
            color: var(--vote-bg);
            font-family: inherit;
            font-size: 1.0625rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: background 0.2s, transform 0.15s;
        }
        .landing-cta:hover {
            background: var(--vote-accent-hover);
            color: var(--vote-bg);
            transform: translateY(-2px);
        }
        .landing-cta i { font-size: 1.25rem; }
        .landing-footer {
            margin-top: 1rem;
        }
        .landing-footer a {
            color: var(--vote-muted);
            font-size: 0.875rem;
            text-decoration: none;
        }
        .landing-footer a:hover { color: var(--vote-accent); }
    </style>
</head>
<body>
    <div class="landing-wrap">
        <section class="landing-hero" aria-label="Vote for your champions">
            <div class="icon-wrap" aria-hidden="true">
                <i class="bi bi-trophy-fill"></i>
            </div>
            <h1>Vote for your champions</h1>
            <p>Support your favourites in the Murang'a 40 Under 40 Awards. Every single vote counts — Cast yours now!!</p>
            <a href="/vote/" class="landing-cta">
                <i class="bi bi-box-arrow-up-right"></i>
                Go to voting
            </a>
        </section>
        <footer class="landing-footer">
            <a href="/admin/">Admin</a>
        </footer>
    </div>
</body>
</html>
