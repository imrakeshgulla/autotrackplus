<?php
require_once __DIR__ . '/../config/config.php';
require_post_csrf(); require_auth();
try { $pdo->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, 'Logged out')")->execute([$_SESSION['user']['id']]); }
catch (Throwable $e) { error_log('Could not record logout.'); }
$_SESSION = [];
if (ini_get('session.use_cookies')) { $params = session_get_cookie_params(); setcookie(session_name(), '', ['expires' => time()-42000, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']); }
session_destroy();
header('Location: ' . BASE_URL . '/public/login.php'); exit;
