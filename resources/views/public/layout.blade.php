<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ config('lab.name') }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/brand/mark-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/brand/mark-192.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1a56db; --primary-light: #e8f0fe; --text: #1e293b; --muted: #64748b;
            --border: #e2e8f0; --bg: #f0f4ff; --card: #ffffff; --accent: #8a277d;
            --ok-bg: #d1fae5; --ok: #047857; --warn-bg: #fff7ed; --warn: #c2410c; --bad-bg: #fee2e2; --bad: #b91c1c;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: var(--bg); color: var(--text); font-size: 14px; line-height: 1.5; }
        .wrap { max-width: 880px; margin: 0 auto; padding: 16px; }
        .lab-head { display: flex; align-items: center; gap: 14px; padding: 18px 20px; background: var(--card); border: 1px solid var(--border); border-radius: 16px 16px 0 0; border-bottom: 3px solid var(--accent); }
        .lab-logo { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, var(--primary), #3b82f6); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0; }
        .lab-name { font-weight: 800; font-size: 18px; letter-spacing: .3px; }
        .lab-meta { font-size: 12px; color: var(--muted); }
        .verified { margin-left: auto; display: inline-flex; align-items: center; gap: 6px; background: var(--ok-bg); color: var(--ok); font-weight: 700; font-size: 12px; padding: 6px 12px; border-radius: 20px; white-space: nowrap; }
        .card { background: var(--card); border: 1px solid var(--border); border-top: 0; padding: 18px 20px; }
        .card:last-child { border-radius: 0 0 16px 16px; }
        .card h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .6px; color: var(--accent); margin: 0 0 12px; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 24px; }
        .kv { display: flex; gap: 8px; min-width: 0; }
        .kv span:first-child { color: var(--muted); min-width: 110px; flex-shrink: 0; }
        .kv span:last-child { font-weight: 600; word-break: break-word; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); padding: 8px; border-bottom: 1.5px solid var(--border); }
        td { padding: 9px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        tr.cat td { background: #f8fafc; font-weight: 700; color: var(--accent); font-size: 12px; letter-spacing: .4px; }
        tr.sub td { font-weight: 700; font-style: italic; }
        tr.ptitle td { font-weight: 800; text-transform: uppercase; font-size: 12.5px; padding-top: 12px; }
        tr.pnote td { font-style: italic; color: var(--muted); font-size: 12.5px; white-space: pre-line; background: #fafafa; }
        .flag { display: inline-block; min-width: 24px; text-align: center; font-weight: 800; border-radius: 6px; padding: 1px 6px; }
        .flag-hi, .flag-c { background: var(--bad-bg); color: var(--bad); }
        .flag-lo { background: var(--warn-bg); color: var(--warn); }
        .val-abn { font-weight: 800; color: var(--bad); }
        .t-unit-inline { display: none; }
        .ref { white-space: pre-line; color: var(--muted); font-size: 13px; }
        .pill { display: inline-block; font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .pill-ok { background: var(--ok-bg); color: var(--ok); }
        .pill-warn { background: var(--warn-bg); color: var(--warn); }
        .pill-muted { background: #f1f5f9; color: var(--muted); }
        .btn { display: inline-flex; align-items: center; gap: 8px; background: var(--primary); color: #fff; text-decoration: none; border: 0; border-radius: 10px; padding: 10px 16px; font-weight: 600; font-size: 14px; cursor: pointer; font-family: inherit; }
        .btn-outline { background: var(--card); color: var(--primary); border: 1.5px solid var(--primary); }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; padding: 18px 0 6px; }
        .foot { text-align: center; color: var(--muted); font-size: 12px; padding: 14px 0 24px; }
        .notes { background: #f8fafc; border-left: 3px solid var(--accent); padding: 10px 14px; border-radius: 6px; white-space: pre-line; }
        .muted { color: var(--muted); }
        a { color: var(--primary); }

        @media (max-width: 640px) {
            .wrap { padding: 10px; }
            .lab-head { flex-wrap: wrap; padding: 14px; }
            .verified { margin-left: 0; }
            .card { padding: 14px; }
            .grid { grid-template-columns: 1fr; }
            .kv span:first-child { min-width: 96px; }
            table.stack thead { display: none; }
            table.stack tr { display: grid; grid-template-columns: 1fr auto; gap: 2px 10px; padding: 10px 4px; border-bottom: 1px solid #f1f5f9; }
            table.stack td { border: 0; padding: 0; }
            table.stack tr.cat, table.stack tr.sub, table.stack tr.ptitle, table.stack tr.pnote { display: block; padding: 0; }
            table.stack tr.cat td, table.stack tr.sub td, table.stack tr.ptitle td, table.stack tr.pnote td { display: block; padding: 8px 6px; }
            table.stack td.t-name { grid-column: 1; font-weight: 600; }
            table.stack td.t-val { grid-column: 2; text-align: right; }
            table.stack td.t-ref { grid-column: 1 / -1; }
            table.stack td.t-unit { display: none; }
            .t-unit-inline { display: inline; }
        }
        @media print {
            body { background: #fff; }
            .actions, .no-print { display: none !important; }
            .lab-head, .card { border-color: #ccc; }
            .wrap { max-width: none; padding: 0; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="lab-head">
        <div class="lab-logo">{{ strtoupper(substr(config('lab.name'), 0, 1)) }}</div>
        <div>
            <div class="lab-name">{{ config('lab.name') }}</div>
            <div class="lab-meta">{{ config('lab.address') }}</div>
            <div class="lab-meta">Ph: {{ config('lab.phone') }} · {{ config('lab.email') }}</div>
        </div>
        <div class="verified" title="This record was loaded directly from the lab's system">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>
            Verified record
        </div>
    </div>
    @yield('content')
    <div class="foot">
        Scanned from a document issued by {{ config('lab.name') }}. If the details here differ from your printout, please contact the lab.
    </div>
</div>
</body>
</html>
