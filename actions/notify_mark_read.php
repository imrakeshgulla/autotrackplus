<?php
require_once __DIR__ . '/../config/config.php'; require_auth();
$id = (int)($_POST['id'] ?? 0);
$pdo->prepare("UPDATE notifications SET read_at=NOW() WHERE id=? AND user_id=?")
    ->execute([$id, $_SESSION['user']['id']]);
header('Location: ' . BASE_URL . '/public/employee/index.php?msg=Notification+dismissed&type=ok');
