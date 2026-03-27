<?php
require_once __DIR__ . '/../config/config.php';

// Live fleet stats — public, no login required
$stats = ['vehicles' => 0, 'due_soon' => 0, 'expired' => 0, 'completed' => 0];
try {
  $row = $pdo->query("
    SELECT
      COUNT(*) AS vehicles,
      SUM(CASE WHEN status='Due Soon'  THEN 1 ELSE 0 END) AS due_soon,
      SUM(CASE WHEN status='Expired'   THEN 1 ELSE 0 END) AS expired,
      SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) AS completed
    FROM vehicles
  ")->fetch();
  if ($row) $stats = $row;
} catch (Exception $e) { /* non-fatal — stats stay at 0 */ }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>AutoTrack+ • Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/app.css?v=20251012">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --navy:   #05080f;
      --navy2:  #0b1120;
      --navy3:  #111c30;
      --blue:   #2563eb;
      --blue2:  #3b82f6;
      --gold:   #f59e0b;
      --muted:  #64748b;
      --border: rgba(255,255,255,.08);
      --text:   #e2e8f0;
      --sub:    #94a3b8;
    }

    html, body { height: 100%; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--navy);
      color: var(--text);
      min-height: 100dvh;
      display: grid;
      grid-template-rows: 1fr auto;
    }

    /* ── Grid layout ── */
    .page {
      display: grid;
      grid-template-columns: 1fr 420px;
      min-height: 100dvh;
    }

    /* ── Hero (left) ── */
    .hero {
      position: relative;
      overflow: hidden;
      padding: 48px 52px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      background: var(--navy2);
    }

    /* animated grid lines */
    .hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background-image:
        linear-gradient(rgba(37,99,235,.07) 1px, transparent 1px),
        linear-gradient(90deg, rgba(37,99,235,.07) 1px, transparent 1px);
      background-size: 48px 48px;
      animation: gridpan 18s linear infinite;
    }

    @keyframes gridpan {
      0%   { background-position: 0 0; }
      100% { background-position: 48px 48px; }
    }

    /* radial glow */
    .hero::after {
      content: '';
      position: absolute;
      top: -80px; left: -80px;
      width: 600px; height: 600px;
      background: radial-gradient(circle, rgba(37,99,235,.18) 0%, transparent 65%);
      pointer-events: none;
    }

    .hero-content { position: relative; z-index: 1; }

    /* logo */
    .logo-row {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 64px;
      animation: fadeup .5s ease both;
    }

    .logo-badge {
      width: 44px; height: 44px;
      background: var(--blue);
      border-radius: 12px;
      display: grid; place-items: center;
      font-family: 'Syne', sans-serif;
      font-weight: 800; font-size: 15px;
      color: #fff;
      box-shadow: 0 0 0 1px rgba(255,255,255,.1), 0 8px 24px rgba(37,99,235,.4);
    }

    .logo-text { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 15px; color: var(--text); }
    .logo-sub  { font-size: 12px; color: var(--sub); margin-top: 1px; }

    /* headline */
    .hero-headline {
      font-family: 'Syne', sans-serif;
      font-weight: 800;
      font-size: clamp(32px, 3.5vw, 52px);
      line-height: 1.1;
      letter-spacing: -.02em;
      color: #fff;
      margin-bottom: 20px;
      animation: fadeup .5s .1s ease both;
    }

    .hero-headline em {
      font-style: normal;
      color: transparent;
      -webkit-text-stroke: 1.5px var(--blue2);
    }

    .hero-desc {
      font-size: 15px;
      color: var(--sub);
      line-height: 1.65;
      max-width: 380px;
      margin-bottom: 52px;
      animation: fadeup .5s .2s ease both;
    }

    /* stat cards */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 10px;
      animation: fadeup .5s .3s ease both;
    }

    .stat-card {
      background: rgba(255,255,255,.04);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 16px 14px;
      transition: background .2s, border-color .2s;
    }

    .stat-card:hover {
      background: rgba(255,255,255,.07);
      border-color: rgba(255,255,255,.14);
    }

    .stat-num {
      font-family: 'Syne', sans-serif;
      font-size: 28px;
      font-weight: 700;
      line-height: 1;
      margin-bottom: 4px;
    }

    .stat-num.blue   { color: var(--blue2); }
    .stat-num.amber  { color: var(--gold); }
    .stat-num.red    { color: #ef4444; }
    .stat-num.green  { color: #22c55e; }

    .stat-label {
      font-size: 11px;
      color: var(--sub);
      letter-spacing: .03em;
      text-transform: uppercase;
    }

    .stat-dot {
      width: 6px; height: 6px;
      border-radius: 50%;
      display: inline-block;
      margin-right: 5px;
      vertical-align: middle;
    }

    /* live pulse */
    .live-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      color: #22c55e;
      background: rgba(34,197,94,.1);
      border: 1px solid rgba(34,197,94,.2);
      border-radius: 99px;
      padding: 4px 10px;
      margin-bottom: 52px;
      animation: fadeup .5s .15s ease both;
    }

    .pulse {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: #22c55e;
      animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50%       { opacity: .5; transform: scale(.7); }
    }

    /* decorative vehicle silhouette */
    .hero-bottom { position: relative; z-index: 1; }

    .vehicle-icon {
      opacity: .06;
      position: absolute;
      right: -20px; bottom: -10px;
      width: 340px;
      pointer-events: none;
    }

    /* ── Form side (right) ── */
    .form-side {
      background: var(--navy);
      border-left: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 48px 40px;
    }

    .form-box { width: 100%; max-width: 340px; }

    .form-title {
      font-family: 'Syne', sans-serif;
      font-weight: 700;
      font-size: 24px;
      color: #fff;
      margin-bottom: 6px;
      animation: fadeup .4s ease both;
    }

    .form-sub {
      font-size: 14px;
      color: var(--sub);
      margin-bottom: 32px;
      animation: fadeup .4s .05s ease both;
    }

    .field-label {
      display: block;
      font-size: 12px;
      font-weight: 500;
      color: var(--sub);
      margin-bottom: 8px;
      letter-spacing: .03em;
      text-transform: uppercase;
    }

    .code-input {
      width: 100%;
      height: 52px;
      background: var(--navy3);
      border: 1px solid var(--border);
      border-radius: 12px;
      color: #fff;
      font-family: 'Syne', sans-serif;
      font-size: 22px;
      font-weight: 700;
      letter-spacing: .25em;
      text-align: center;
      outline: none;
      transition: border-color .2s, box-shadow .2s;
      margin-bottom: 6px;
      padding: 0 16px;
    }

    .code-input::placeholder { color: var(--muted); letter-spacing: .1em; font-size: 18px; }

    .code-input:focus {
      border-color: var(--blue);
      box-shadow: 0 0 0 3px rgba(37,99,235,.15);
    }

    .code-hint {
      font-size: 12px;
      color: var(--muted);
      text-align: center;
      margin-bottom: 24px;
    }

    .error-msg {
      background: rgba(239,68,68,.1);
      border: 1px solid rgba(239,68,68,.25);
      color: #fca5a5;
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 13px;
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .submit-btn {
      width: 100%;
      height: 52px;
      background: var(--blue);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-family: 'Syne', sans-serif;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: background .2s, transform .1s, box-shadow .2s;
      box-shadow: 0 4px 16px rgba(37,99,235,.35);
      margin-bottom: 24px;
    }

    .submit-btn:hover  { background: #1d4ed8; box-shadow: 0 6px 20px rgba(37,99,235,.45); }
    .submit-btn:active { transform: scale(.98); }

    .arrow-icon { font-size: 18px; transition: transform .2s; }
    .submit-btn:hover .arrow-icon { transform: translateX(3px); }

    .register-link {
      text-align: center;
      font-size: 13px;
      color: var(--sub);
    }

    .register-link a {
      color: var(--blue2);
      text-decoration: none;
      font-weight: 500;
    }

    .register-link a:hover { text-decoration: underline; }

    /* dots decoration */
    .dots {
      display: flex;
      gap: 6px;
      justify-content: center;
      margin-top: 36px;
      opacity: .2;
    }

    .dot {
      width: 4px; height: 4px;
      border-radius: 50%;
      background: var(--sub);
    }

    /* animation */
    @keyframes fadeup {
      from { opacity: 0; transform: translateY(14px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* success login anim */
    .submit-btn.loading {
      pointer-events: none;
      opacity: .7;
    }

    /* ── Responsive ── */
    @media (max-width: 860px) {
      .page { grid-template-columns: 1fr; grid-template-rows: auto 1fr; }
      .hero {
        padding: 32px 28px 36px;
        min-height: auto;
      }
      .hero-headline { font-size: 28px; margin-bottom: 14px; }
      .hero-desc { margin-bottom: 28px; font-size: 14px; }
      .stats-row { grid-template-columns: repeat(2, 1fr); }
      .logo-row { margin-bottom: 36px; }
      .live-badge { margin-bottom: 28px; }
      .form-side { border-left: none; border-top: 1px solid var(--border); padding: 36px 28px; }
      .vehicle-icon { display: none; }
    }
  </style>
</head>
<body>
<div class="page">

  <!-- ── HERO LEFT ── -->
  <div class="hero">
    <div class="hero-content">
      <div class="logo-row">
        <div class="logo-badge">AT+</div>
        <div>
          <div class="logo-text">AutoTrack+</div>
          <div class="logo-sub">Vehicle & Staff Portal</div>
        </div>
      </div>

      <div class="live-badge">
        <div class="pulse"></div>
        Live fleet data
      </div>

      <h1 class="hero-headline">
        Keep your<br>fleet <em>moving</em><br>forward.
      </h1>

      <p class="hero-desc">
        Track service schedules, insurance expiries, and repair histories for every vehicle — all in one place.
      </p>

      <!-- LIVE STATS -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-num blue"><?php echo (int)$stats['vehicles']; ?></div>
          <div class="stat-label">
            <span class="stat-dot" style="background:#3b82f6"></span>Vehicles
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-num amber"><?php echo (int)$stats['due_soon']; ?></div>
          <div class="stat-label">
            <span class="stat-dot" style="background:#f59e0b"></span>Due soon
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-num red"><?php echo (int)$stats['expired']; ?></div>
          <div class="stat-label">
            <span class="stat-dot" style="background:#ef4444"></span>Expired
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-num green"><?php echo (int)$stats['completed']; ?></div>
          <div class="stat-label">
            <span class="stat-dot" style="background:#22c55e"></span>Completed
          </div>
        </div>
      </div>
    </div>

    <!-- decorative car SVG outline -->
    <div class="hero-bottom">
      <svg class="vehicle-icon" viewBox="0 0 400 160" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M20 110 L60 70 L120 45 L200 38 L280 45 L340 70 L380 110 L380 130 L20 130 Z" stroke="white" stroke-width="3" fill="none"/>
        <circle cx="90"  cy="130" r="22" stroke="white" stroke-width="3" fill="none"/>
        <circle cx="310" cy="130" r="22" stroke="white" stroke-width="3" fill="none"/>
        <path d="M120 70 L130 48 L200 44 L270 48 L280 70 Z" stroke="white" stroke-width="2" fill="none" opacity=".6"/>
      </svg>
    </div>
  </div>

  <!-- ── FORM RIGHT ── -->
  <div class="form-side">
    <div class="form-box">
      <div class="form-title">Employee login</div>
      <div class="form-sub">Enter your 6-digit employee code to continue.</div>

      <?php if (!empty($_GET['err'])): ?>
        <div class="error-msg">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="#f87171" stroke-width="1.5"/><path d="M8 5v3.5M8 10.5v.5" stroke="#f87171" stroke-width="1.5" stroke-linecap="round"/></svg>
          Invalid or unverified employee code.
        </div>
      <?php endif; ?>

      <?php if (!empty($_GET['msg']) && $_GET['msg'] === 'verified'): ?>
        <div style="background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);color:#86efac;border-radius:10px;padding:10px 14px;font-size:13px;margin-bottom:18px;display:flex;align-items:center;gap:8px;">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="#86efac" stroke-width="1.5"/><path d="M5 8l2 2 4-4" stroke="#86efac" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Account verified! You can now log in.
        </div>
      <?php endif; ?>

      <form method="post" action="<?php echo BASE_URL; ?>/actions/auth_login.php" id="login-form">
        <label class="field-label" for="emp_code">Employee code</label>
        <input
          class="code-input"
          type="text"
          id="emp_code"
          name="emp_code"
          maxlength="6"
          pattern="\d{6}"
          placeholder="······"
          inputmode="numeric"
          autocomplete="off"
          autofocus
          required
        >
        <div class="code-hint">6 digits, numbers only</div>

        <button class="submit-btn" type="submit" id="submit-btn">
          Continue <span class="arrow-icon">→</span>
        </button>
      </form>

      <div class="register-link">
        No account? <a href="<?php echo BASE_URL; ?>/public/register.php">Request access</a>
      </div>

      <div class="dots">
        <div class="dot"></div><div class="dot"></div><div class="dot"></div>
        <div class="dot"></div><div class="dot"></div>
      </div>
    </div>
  </div>

</div>

<script>
  // Only allow digits in the code input
  document.getElementById('emp_code').addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
  });

  // Loading state on submit
  document.getElementById('login-form').addEventListener('submit', function() {
    const btn = document.getElementById('submit-btn');
    btn.classList.add('loading');
    btn.innerHTML = 'Signing in…';
  });
</script>
</body>
</html>