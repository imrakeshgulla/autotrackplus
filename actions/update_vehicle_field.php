<?php
require_once __DIR__ . '/../config/config.php'; require_auth();

$id    = (int)($_POST['id'] ?? 0);
$field = $_POST['field'] ?? '';
$value = trim($_POST['value'] ?? '');

$ok = in_array($field, ['plate','year'], true);
if (!$ok || $id <= 0) { http_response_code(400); exit('Bad request'); }

if ($field === 'year') { $value = (int)$value; if ($value < 1900 || $value > 2100) { http_response_code(422); exit('Invalid year'); } }

$stmt = $pdo->prepare("UPDATE vehicles SET `$field`=? WHERE id=? AND user_id=?");
$stmt->execute([$value, $id, $_SESSION['user']['id']]);

header('Content-Type: application/json');
echo json_encode(['ok'=>true,'field'=>$field,'value'=>$value]);
