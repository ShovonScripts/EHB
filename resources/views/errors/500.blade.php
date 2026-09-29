{{--
    500 error page (NFR-009 — "user-facing errors (404, 500, form validation)
    shall be handled gracefully with on-brand error pages").

    Deliberately self-contained: no @extends/x-app-layout, no database call,
    no SiteSettings read, no Vite asset. A 500 is frequently *caused* by the
    database or the session layer, so anything that touches them here risks a
    secondary failure inside the error handler. Colours are inlined from
    DESIGN_SYSTEM.md §3 so the page still looks on-brand without the build.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Something went wrong</title>
    <style>
        :root {
            --ink: #1a1a1a;
            --paper: #faf9f6;
            --accent: #8c1d18;
            --muted: #5b5b5b;
            --hairline: #e4e1d9;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background-color: var(--paper);
            color: var(--ink);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 1.0625rem;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
        }
        main { max-width: 34rem; width: 100%; text-align: center; }
        .eyebrow {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--accent);
        }
        h1 {
            margin: 0.75rem 0 0;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(1.75rem, 5vw, 2.5rem);
            line-height: 1.2;
        }
        p { margin: 1rem 0 0; color: var(--muted); }
        .actions {
            margin-top: 2rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
        }
        a {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.025em;
            text-decoration: none;
            border: 1px solid var(--accent);
            color: var(--accent);
            background: transparent;
        }
        a.primary { background-color: var(--accent); color: var(--paper); }
        a:hover { filter: brightness(0.92); }
        a:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        hr {
            margin: 2rem auto 0;
            max-width: 6rem;
            border: 0;
            border-top: 1px solid var(--hairline);
        }
    </style>
</head>
<body>
    <main>
        <p class="eyebrow">Server error</p>
        <h1>Something went wrong</h1>
        <p>
            An unexpected error occurred while loading this page. The problem has been
            logged. Please try again in a few moments.
        </p>
        <div class="actions">
            <a class="primary" href="/">Back to home</a>
            <a href="/archive">Browse the archive</a>
        </div>
        <hr>
    </main>
</body>
</html>
