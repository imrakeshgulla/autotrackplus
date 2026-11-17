<?php
require_once __DIR__ . '/../config/config.php'; require_admin(); require_post_csrf();
$id = (int)($_POST['id'] ?? 0);
if (!isset($_POST['confirm_identity'])) { http_response_code(400); exit('Confirm identity and email first.'); }
$q = $pdo->prepare('SELECT id FROM users WHERE id=?'); $q->execute([$id]);
if (!$q->fetch()) { http_response_code(404); exit('Account not found.'); }
$pdo->prepare('UPDATE users SET password_setup_authorized_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);
try { issue_password_link($id); $_SESSION['admin_message'] = 'Password invitation sent (expires in 30 minutes).'; }
catch (Throwable $e) { $_SESSION['admin_message'] = 'Email delivery failed. Check SMTP and retry.'; }
header('Location: ' . BASE_URL . '/public/admin/index.php'); exit;
