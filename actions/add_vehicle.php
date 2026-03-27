<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config/config.php';
require_auth();

$userId          = $_SESSION['user']['id'];
$make_model      = trim($_POST['make_model'] ?? '');
$year            = (int)($_POST['year'] ?? 0);
$plate           = trim($_POST['plate'] ?? '');
$next_service    = $_POST['next_service'] ?? null;
$insurance_expiry = $_POST['insurance_expiry'] ?? null;
$mileage         = (int)($_POST['mileage'] ?? 0);

if ($make_model === '' || $year <= 0 || $plate === '') {
  header('Location: ' . BASE_URL . '/public/employee/index.php');
  exit;
}

try {
  $stmt = $pdo->prepare("
    INSERT INTO vehicles
      (user_id, make_model, year, plate, next_service, insurance_expiry, mileage, status, miles_left)
    VALUES
      (:uid, :mm, :yr, :pl, :ns, :ie, :mi, 'Due Soon', 0)
  ");
  $stmt->execute([
    ':uid' => $userId,
    ':mm'  => $make_model,
    ':yr'  => $year,
    ':pl'  => $plate,
    ':ns'  => $next_service ?: null,
    ':ie'  => $insurance_expiry ?: null,
    ':mi'  => $mileage,
  ]);

  // Log activity
  $pdo->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, ?)")
      ->execute([$userId, "Added vehicle: {$make_model} ({$year}) – Plate: {$plate}"]);

} catch (Exception $e) {
  // Optional: error_log($e->getMessage());
}

header('Location: ' . BASE_URL . '/public/employee/index.php');
exit;
