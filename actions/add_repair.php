<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); } // FIX: was missing
require_once __DIR__ . '/../config/config.php';
require_auth();

$vid  = (int)($_POST['vehicle_id'] ?? 0);
$note = trim($_POST['note'] ?? '');
$tag  = $_POST['tag'] ?? 'Due Soon';

$allowed_tags = ['Due Soon', 'Expired', 'Completed'];
if (!in_array($tag, $allowed_tags, true)) $tag = 'Due Soon';

if ($vid && $note !== '') {
  // Only allow adding repair to vehicles owned by this user
  $check = $pdo->prepare("SELECT id FROM vehicles WHERE id = ? AND user_id = ?");
  $check->execute([$vid, $_SESSION['user']['id']]);
  if ($check->fetch()) {
    $pdo->prepare("INSERT INTO repairs (vehicle_id, note, tag) VALUES (?, ?, ?)")
        ->execute([$vid, $note, $tag]);

    // Log activity
    $pdo->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, ?)")
        ->execute([$_SESSION['user']['id'], "Added repair: {$note} (tag: {$tag})"]);
  }
}

header('Location: ' . BASE_URL . '/public/employee/index.php');
exit;
