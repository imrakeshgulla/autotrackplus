<?php
require_once __DIR__ . '/../config/config.php'; require_auth();
if ($_SESSION['user']['role'] !== 'admin'){ header('Location: ' . BASE_URL . '/public/login.php'); exit; }

$id = (int)($_POST['id'] ?? 0);
if ($id>0 && $id !== $_SESSION['user']['id']) {
  // deleting user cascades vehicles (foreign key ON DELETE CASCADE already in schema)
  $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$id]);
}
header('Location: ' . BASE_URL . '/public/admin/index.php');
