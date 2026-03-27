<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/config.php';
require_auth();

// FIX: was hardcoded fake data — now pulls real activity from DB
$stmt = $pdo->prepare("
  SELECT action, created_at
  FROM activity_log
  WHERE user_id = ?
  ORDER BY created_at DESC
  LIMIT 100
");
$stmt->execute([$_SESSION['user']['id']]);
$history = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>History • AutoTrack+</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/app.css?v=20251012">
  <style>
    :root { --mx: 1200px }
    body { margin: 0; background: #f3f5f8; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
    main { max-width: var(--mx); margin-inline: auto; padding: 12px 16px }
    .topbar { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: #0d1321; color: #fff; border-radius: 14px; margin: 8px 0 16px }
    .brand { display: flex; align-items: center; gap: 10px }
    .brand .logo { width: 36px; height: 36px; border-radius: 12px; background: linear-gradient(180deg, #3b82f6, #2563eb); display: grid; place-items: center; color: #fff; font-weight: 800 }
    .pill-btn { background: rgba(255,255,255,.08); color: #fff; border: 1px solid rgba(255,255,255,.18); border-radius: 999px; padding: 8px 14px; text-decoration: none }
    .list { display: flex; flex-direction: column; gap: 10px }
    .item { background: #fff; border: 1px solid rgba(0,0,0,.06); border-radius: 12px; padding: 12px 14px }
    .when { color: #6b7280; font-size: 12px; margin-right: 6px }
    .empty-state { text-align: center; color: #6b7280; padding: 40px; background: #fff; border-radius: 12px; border: 1px dashed #e5e7eb }
    @media (max-width: 640px) { .topbar { border-radius: 0 } }
  </style>
</head>
<body>
  <main>
    <div class="topbar">
      <div class="brand">
        <div class="logo">AT+</div>
        <div><h1 style="margin:0;font-size:16px">History</h1><small>Recent activity</small></div>
      </div>
      <a class="pill-btn" href="<?= BASE_URL ?>/public/employee/index.php">Dashboard</a>
    </div>

    <section class="list">
      <?php if (empty($history)): ?>
        <div class="empty-state">No activity recorded yet. Start by adding a vehicle!</div>
      <?php else: ?>
        <?php foreach ($history as $row): ?>
          <div class="item">
            <span class="when"><?= htmlspecialchars($row['created_at']) ?></span>
            <?= htmlspecialchars($row['action']) ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
