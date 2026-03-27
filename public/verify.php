<?php
require_once __DIR__ . '/../config/config.php';

$token = $_GET['token'] ?? '';
if (!$token) { echo "Invalid token"; exit; }

$pdo->beginTransaction();
try {
  $sel = $pdo->prepare("SELECT id FROM users WHERE verify_token=? AND is_verified=0");
  $sel->execute([$token]);
  if ($u = $sel->fetch()){
    $upd = $pdo->prepare("UPDATE users SET is_verified=1, verify_token=NULL WHERE id=?");
    $upd->execute([$u['id']]);
    $pdo->commit();
    header('Location: ' . BASE_URL . '/public/login.php?msg=verified');
  } else { $pdo->rollBack(); echo "Token invalid or already verified."; }
} catch(Exception $e){
  $pdo->rollBack(); echo "Error verifying.";
}
