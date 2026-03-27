<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../config/config.php';
require_auth();

function h($s) { return htmlspecialchars(isset($s) ? $s : '', ENT_QUOTES, 'UTF-8'); }
function nice($d) { $t = strtotime($d); return $t ? date('M j, Y', $t) : '—'; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: ' . BASE_URL . '/public/employee/index.php'); exit; }

// Fetch vehicle (must belong to current user)
$stmt = $pdo->prepare("SELECT * FROM vehicles WHERE id = :id AND user_id = :uid LIMIT 1");
$stmt->execute([':id' => $id, ':uid' => $_SESSION['user']['id']]);
$veh = $stmt->fetch();
if (!$veh) { header('Location: ' . BASE_URL . '/public/employee/index.php'); exit; }

// FIX: fetch real repairs from DB (was hardcoded "No entries yet")
$repStmt = $pdo->prepare("SELECT * FROM repairs WHERE vehicle_id = ? ORDER BY created_at DESC");
$repStmt->execute([$id]);
$repairs = $repStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= h($veh['make_model']) ?> Details • AutoTrack+</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/app.css?v=20251012">
  <style>
    body { margin: 0; background: #f3f5f8; font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
    main { max-width: 1100px; margin: 0 auto; padding: 16px }
    .topbar { display: flex; align-items: center; justify-content: space-between; background: #0d1321; color: #fff; border-radius: 14px; padding: 12px 16px; margin: 12px 0 }
    .logo { width: 36px; height: 36px; border-radius: 12px; background: #2563eb; color: #fff; display: grid; place-items: center; font-weight: 800 }
    .card { background: #fff; border: 1px solid rgba(0,0,0,.08); border-radius: 14px; padding: 16px; box-shadow: 0 6px 16px rgba(16,24,40,.06); margin-bottom: 14px }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px }
    .label { color: #6b7280; font-size: 12px; margin-bottom: 4px }
    .value { font-size: 20px; font-weight: 700 }
    .pill { background: #111827; color: #fff; border-radius: 999px; padding: 8px 12px; text-decoration: none }
    .badge-tag { display: inline-block; padding: 3px 8px; border-radius: 999px; font-size: 12px; font-weight: 600 }
    .badge-tag.due  { background: #eef6ff; color: #1d4ed8; border: 1px solid #bfdcff }
    .badge-tag.exp  { background: #fff1f2; color: #b91c1c; border: 1px solid #fecdd3 }
    .badge-tag.comp { background: #e7f8ee; color: #166534; border: 1px solid #bce7d1 }
    .repair-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 8px }
    .repair-note { font-weight: 600; font-size: 14px }
    .repair-meta { font-size: 12px; color: #6b7280; margin-top: 2px }
    .btn-del { background: transparent; border: 1px solid #ef4444; color: #ef4444; border-radius: 8px; padding: 4px 10px; cursor: pointer; font-size: 13px }
    .empty-state { text-align: center; color: #6b7280; padding: 24px; border: 1px dashed #e5e7eb; border-radius: 10px }
  </style>
</head>
<body>
  <main>
    <div class="topbar">
      <div style="display:flex;gap:10px;align-items:center">
        <div class="logo">AT+</div>
        <div>
          <div style="font-weight:700"><?= h($veh['make_model']) ?> (<?= (int)$veh['year'] ?>)</div>
          <small>Plate: <?= h($veh['plate']) ?> • Status: <?= h($veh['status']) ?></small>
        </div>
      </div>
      <a class="pill" href="<?= BASE_URL ?>/public/employee/index.php">← Back</a>
    </div>

    <!-- Vehicle Info -->
    <div class="card">
      <div class="grid">
        <div><div class="label">Mileage</div><div class="value"><?= number_format((int)$veh['mileage']) ?></div></div>
        <div><div class="label">Next Service</div><div class="value"><?= nice($veh['next_service']) ?></div></div>
        <div><div class="label">Insurance Expiry</div><div class="value"><?= nice($veh['insurance_expiry']) ?></div></div>
        <div><div class="label">Miles Left</div><div class="value"><?= number_format((int)$veh['miles_left']) ?></div></div>
      </div>
    </div>

    <!-- Repairs -->
    <div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
        <strong style="font-size:16px">Repairs & Notes</strong>
        <span style="font-size:12px;color:#6b7280"><?= count($repairs) ?> record(s)</span>
      </div>

      <!-- Add repair form -->
      <form method="post" action="<?= BASE_URL ?>/actions/add_repair.php" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
        <input type="hidden" name="vehicle_id" value="<?= $veh['id'] ?>">
        <input class="input" name="note" placeholder="e.g., Brake pads replaced" required style="flex:1;min-width:200px">
        <select class="input" name="tag" style="width:140px">
          <option value="Due Soon">Due Soon</option>
          <option value="Expired">Expired</option>
          <option value="Completed">Completed</option>
        </select>
        <button class="btn" type="submit" style="width:auto">Add Repair</button>
      </form>

      <!-- FIX: was always showing "No entries yet", now shows real repairs -->
      <?php if (empty($repairs)): ?>
        <div class="empty-state">No repairs recorded yet. Add your first repair above.</div>
      <?php else: ?>
        <?php foreach ($repairs as $r):
          $tagClass = $r['tag'] === 'Completed' ? 'comp' : ($r['tag'] === 'Expired' ? 'exp' : 'due');
        ?>
          <div class="repair-row">
            <div>
              <div class="repair-note"><?= h($r['note']) ?></div>
              <div class="repair-meta"><?= h($r['created_at']) ?></div>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
              <span class="badge-tag <?= $tagClass ?>"><?= h($r['tag']) ?></span>
              <form method="post" action="<?= BASE_URL ?>/actions/delete_repair.php" onsubmit="return confirm('Delete this repair?')">
                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                <button class="btn-del" type="submit">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
