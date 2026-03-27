<?php
require_once __DIR__ . '/../config/config.php';

$code = preg_replace('/\D/', '', $_POST['emp_code'] ?? '');
if (strlen($code) !== 6) {
  header('Location: ' . BASE_URL . '/public/login.php?err=1');
  exit;
}

$stmt = $pdo->prepare("SELECT id, first_name, last_name, email, emp_code, role, is_verified FROM users WHERE emp_code = ?");
$stmt->execute([$code]);
$user = $stmt->fetch();

if (!$user || !$user['is_verified']) {
  header('Location: ' . BASE_URL . '/public/login.php?err=1');
  exit;
}

// FIX: regenerate session ID after login to prevent session fixation attacks
session_regenerate_id(true);

$_SESSION['user'] = [
  'id'       => $user['id'],
  'name'     => $user['first_name'] . ' ' . $user['last_name'],
  'emp_code' => $user['emp_code'],
  'role'     => $user['role'],
];

// Log the login action
try {
  $pdo->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, ?)")
      ->execute([$user['id'], 'Logged in']);
} catch (Exception $e) { /* non-fatal */ }

if ($user['role'] === 'admin') {
  header('Location: ' . BASE_URL . '/public/admin/index.php');
} else {
  header('Location: ' . BASE_URL . '/public/employee/index.php');
}
exit;
