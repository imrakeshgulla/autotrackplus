<?php
require_once __DIR__ . '/../config/config.php'; require_auth();
$id = (int)($_POST['id']??0); $status = $_POST['status']??'Due Soon';
$upd = $pdo->prepare("UPDATE vehicles SET status=? WHERE id=? AND user_id=?");
$upd->execute([$status,$id,$_SESSION['user']['id']]);
header('Location:' . BASE_URL . '/public/employee/index.php');
