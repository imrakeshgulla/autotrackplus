<?php
require_once __DIR__ . '/../config/config.php'; require_auth();
$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
  // Only allow delete if the repair belongs to one of the current user's vehicles
  $stmt = $pdo->prepare("
    DELETE r FROM repairs r
    JOIN vehicles v ON v.id=r.vehicle_id
    WHERE r.id=? AND v.user_id=?
  ");
  $stmt->execute([$id, $_SESSION['user']['id']]);
}
header('Location:' . BASE_URL . '/public/employee/index.php');
