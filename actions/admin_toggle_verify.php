<?php
require_once __DIR__ . '/../config/config.php'; require_auth();
if ($_SESSION['user']['role'] !== 'admin'){ header('Location: ' . BASE_URL . '/public/login.php'); exit; }

$id = (int)($_POST['id'] ?? 0);
$to = (int)($_POST['to'] ?? 0);
if ($id>0){
  $pdo->prepare("UPDATE users SET is_verified=? WHERE id=?")->execute([$to, $id]);
}
header('Location: ' . BASE_URL . '/public/admin/index.php');