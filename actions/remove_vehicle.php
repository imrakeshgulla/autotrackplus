<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/config.php';
require_auth();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
  // Get vehicle name for the log before deleting
  $veh = $pdo->prepare("SELECT make_model, year FROM vehicles WHERE id = ? AND user_id = ?");
  $veh->execute([$id, $_SESSION['user']['id']]);
  $v = $veh->fetch();

  $pdo->prepare("DELETE FROM vehicles WHERE id = ? AND user_id = ?")
      ->execute([$id, $_SESSION['user']['id']]);

  if ($v) {
    $pdo->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, ?)")
        ->execute([$_SESSION['user']['id'], "Removed vehicle: {$v['make_model']} ({$v['year']})"]);
  }
}

header('Location: ' . BASE_URL . '/public/employee/index.php');
exit;
