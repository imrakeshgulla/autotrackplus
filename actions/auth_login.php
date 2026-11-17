<?php
require_once __DIR__ . '/../config/config.php';
require_post_csrf();
$code = is_string($_POST['emp_code'] ?? null) ? $_POST['emp_code'] : '';
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$accountAllowed = throttle('login-account', $code, 10);
$ipAllowed = throttle('login-ip', $_SERVER['REMOTE_ADDR'] ?? '', 50);
if (!$accountAllowed || !$ipAllowed) { http_response_code(429); exit('Too many attempts. Try again in 15 minutes.'); }
$q = $pdo->prepare('SELECT * FROM users WHERE emp_code=?');
$q->execute([preg_match('/^[0-9]{6}$/D', $code) ? $code : '']);
$user = $q->fetch();
// A fixed dummy hash keeps nonexistent and passwordless accounts on the verification path.
$dummy = '$argon2id$v=19$m=65536,t=4,p=1$c2FmZXNhbHQxMjM0NTY3OA$6Cp+8EtxR2YEAs5Ws7TYHMDOWH3wa4+zBhKBG4lrGV4';
$valid = strlen($password) <= 512 && password_verify($password, $user['password_hash'] ?? $dummy);
if (!$valid || !$user || !$user['is_verified'] || !$user['password_hash']) {
    header('Location: ' . BASE_URL . '/public/login.php?err=1'); exit;
}
if (password_needs_rehash($user['password_hash'], PASSWORD_ARGON2ID)) {
    $pdo->prepare('UPDATE users SET password_hash=? WHERE id=? AND password_hash=?')->execute([password_hash($password, PASSWORD_ARGON2ID), $user['id'], $user['password_hash']]);
}
session_regenerate_id(true);
$_SESSION = ['user' => ['id' => $user['id'], 'name' => $user['first_name'] . ' ' . $user['last_name'], 'emp_code' => $user['emp_code'], 'role' => $user['role'], 'session_version' => $user['session_version']], 'authenticated_at' => time(), 'last_seen' => time()];
$pdo->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, 'Logged in')")->execute([$user['id']]);
header('Location: ' . BASE_URL . '/public/' . ($user['role'] === 'admin' ? 'admin' : 'employee') . '/index.php'); exit;
