<?php
require_once __DIR__ . '/../config/config.php';

// FIX: log activity before destroying session
if (isset($_SESSION['user'])) {
  try {
    $pdo->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, ?)")
        ->execute([$_SESSION['user']['id'], 'Logged out']);
  } catch (Exception $e) { /* non-fatal */ }
}

session_destroy();
header('Location: ' . BASE_URL . '/public/login.php');
exit; // FIX: was missing exit
