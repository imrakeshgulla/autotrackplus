<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/config.php';
require_auth();

$userId = $_SESSION['user']['id'];

// FIX: was hardcoded fake data — now pulls real notifications from DB
$stmt = $pdo->prepare("
  SELECT n.id, n.type, n.due_on, n.read_at, v.make_model, v.plate
  FROM notifications n
  JOIN vehicles v ON v.id = n.vehicle_id
  WHERE n.user_id = ?
  ORDER BY n.read_at IS NOT NULL ASC, n.due_on ASC
");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Notifications • AutoTrack+</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/app.css?v=20251012">
  <style>
    body { margin: 0; background: #f3f5f8; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
    main { max-width: 1180px; margin: 0 auto; padding: 8px 16px 32px }
    .notif { background: #fff; border-radius: 12px; padding: 12px 14px; border: 1px solid rgba(0,0,0,.06); display: flex; justify-content: space-between; align-items: center; gap: 12px }
    .notif.service  { border-left: 4px solid #f59e0b }
    .notif.insurance { border-left: 4px solid #3b82f6 }
    .notif.read { opacity: 0.5 }
    .notif-text { font-size: 14px }
    .notif-meta { font-size: 12px; color: #6b7280; margin-top: 2px }
    .empty-state { text-align: center; color: #6b7280; padding: 40px; background: #fff; border-radius: 12px; border: 1px dashed #e5e7eb }
    .pill-btn { background: rgba(255,255,255,.08); color: #fff; border: 1px solid rgba(255,255,255,.18); border-radius: 999px; padding: 8px 14px; text-decoration: none }
    .btn-sm { height: 32px; padding: 0 12px; border-radius: 8px; background: #111827; color: #fff; border: none; cursor: pointer; font-size: 13px; white-space: nowrap }
  </style>
</head>
<body>
  <div class="topbar">
    <div class="brand" style="display:flex;align-items:center;gap:10px">
      <div style="width:36px;height:36px;border-radius:12px;background:#2563eb;color:#fff;display:grid;place-items:center;font-weight:800">AT+</div>
      <div><strong>Notifications</strong><br><small>Your alerts</small></div>
    </div>
    <div class="top-actions">
      <a class="pill-btn" href="<?= BASE_URL ?>/public/employee/index.php">Dashboard</a>
    </div>
  </div>

  <main>
    <?php if (empty($notifications)): ?>
      <div class="empty-state">🎉 No notifications. You're all caught up!</div>
    <?php else: ?>
      <ul style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px">
        <?php foreach ($notifications as $n): ?>
          <li class="notif <?= htmlspecialchars($n['type']) ?> <?= $n['read_at'] ? 'read' : '' ?>">
            <div>
              <div class="notif-text">
                <?= $n['type'] === 'service' ? '🔧 Service due' : '🛡️ Insurance expiring' ?>
                — <strong><?= htmlspecialchars($n['make_model']) ?></strong>
                (<?= htmlspecialchars($n['plate']) ?>)
              </div>
              <div class="notif-meta">
                Due: <?= htmlspecialchars($n['due_on']) ?>
                <?= $n['read_at'] ? ' • Dismissed on ' . htmlspecialchars($n['read_at']) : '' ?>
              </div>
            </div>
            <?php if (!$n['read_at']): ?>
              <form method="post" action="<?= BASE_URL ?>/actions/notify_mark_read.php">
                <input type="hidden" name="id" value="<?= $n['id'] ?>">
                <button class="btn-sm">Dismiss</button>
              </form>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </main>
</body>
</html>
