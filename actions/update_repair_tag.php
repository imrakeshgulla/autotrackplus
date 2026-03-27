<?php
// actions/update_repair_tag.php
require_once __DIR__ . '/../config/config.php';
require_auth();

if (($_SESSION['user']['role'] ?? '') !== 'employee') {
  http_response_code(403);
  echo 'forbidden';
  exit;
}

$userId = (int)($_SESSION['user']['id'] ?? 0);
$repairId = (int)($_POST['id'] ?? 0);
$tag      = trim($_POST['tag'] ?? '');

$allowed = ['Due Soon','Expired','Completed'];
if (!$userId || !$repairId || !in_array($tag, $allowed, true)) {
  http_response_code(422);
  echo 'bad request';
  exit;
}

/*
  Securely update only if the repair belongs to a vehicle owned by this user.
  (Assumes $pdo comes from config.php)
*/
$sql = "UPDATE repairs r
        JOIN vehicles v ON v.id = r.vehicle_id
        SET r.tag = ?
        WHERE r.id = ? AND v.user_id = ?
        LIMIT 1";

$ok = $pdo->prepare($sql)->execute([$tag, $repairId, $userId]);

header('Content-Type: application/json');
echo json_encode(['ok' => (bool)$ok]);
