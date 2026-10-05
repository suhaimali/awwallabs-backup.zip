<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Service temporarily unavailable</title>
    <link rel="icon" type="image/png" href="/images/brand/mark-32.png">
    <style>
        :root { --primary: #1a56db; --text: #1e293b; --muted: #64748b; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px;
               font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #f0f4ff; color: var(--text); }
        .card { width: 100%; max-width: 420px; background: #fff; border-radius: 18px; padding: 32px 26px; text-align: center; box-shadow: 0 10px 40px rgba(26,86,219,.12); }
        .logo { width: 84px; height: 84px; object-fit: contain; margin-bottom: 14px; }
        .icon { width: 56px; height: 56px; margin: 0 auto 10px; color: #d97706; }
        h1 { font-size: 21px; margin: 0 0 8px; }
        p { color: var(--muted); font-size: 14.5px; line-height: 1.55; margin: 0 0 20px; }
        button { background: var(--primary); color: #fff; border: 0; padding: 11px 26px; border-radius: 10px; font-size: 15px; font-weight: 600; cursor: pointer; width: 100%; max-width: 240px; }
        button:disabled { opacity: .7; cursor: wait; }
        .hint { font-size: 12.5px; color: var(--muted); margin-top: 14px; min-height: 18px; }
    </style>
</head>
<body>
<main class="card" role="alert">
    <img class="logo" src="/images/brand/mark-192.png" alt="Suhaimsoft Lab" onerror="this.style.display='none'">
    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path stroke-linecap="round" d="M7 7.5h.01M7 16.5h.01"/></svg>
    <h1>We’ll be right back</h1>
    <p>The lab server is not responding at the moment. Your data is safe — please wait a few seconds; this page will reconnect on its own.</p>
    <button type="button" id="retry">Try again</button>
    <div class="hint" id="hint" aria-live="polite"></div>
</main>
<script>
    (function () {
        var btn = document.getElementById('retry'), hint = document.getElementById('hint'), left = 15, timer;
        function retry() {
            btn.disabled = true; btn.textContent = 'Checking…';
            window.location.reload();
        }
        function tick() {
            if (false && !navigator.onLine) { hint.textContent = 'Waiting for your internet connection…'; return; }
            left -= 1;
            hint.textContent = 'Retrying automatically in ' + left + 's';
            if (left <= 0) { clearInterval(timer); retry(); }
        }
        btn.addEventListener('click', retry);
        window.addEventListener('online', retry);
        timer = setInterval(tick, 1000);
    })();
</script>
</body>
</html>
