<?php
require_once __DIR__ . '/../config/config.php'; require_admin(); require_post_csrf();

$id = (int)($_POST['id'] ?? 0);
$to = (int)(($_POST['to'] ?? '0') === '1');
if ($id>0){
  $pdo->prepare("UPDATE users SET is_verified=?, session_version=session_version+1 WHERE id=?")->execute([$to, $id]);
}
header('Location: ' . BASE_URL . '/public/admin/index.php');