<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/config.php';
$options = getopt('', ['code:', 'email:', 'confirm-identity']);
$code = $options['code'] ?? '';
$email = $options['email'] ?? '';
if (!preg_match('/^[0-9]{6}$/D', $code) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !array_key_exists('confirm-identity', $options)) {
    fwrite(STDERR, "Usage: php bin/issue-password-setup.php --code=999999 --email=trusted-address@example.com --confirm-identity\nConfirm the employee identity and destination email through trusted records first.\n"); exit(1);
}
$q = $pdo->prepare('SELECT id FROM users WHERE emp_code=?'); $q->execute([$code]);
$id = $q->fetchColumn();
if (!$id) { fwrite(STDERR, "Account not found.\n"); exit(1); }
try {
    $pdo->prepare('UPDATE users SET email=?, password_setup_authorized_at=UTC_TIMESTAMP(), session_version=session_version+1 WHERE id=?')->execute([$email, $id]);
    issue_password_link((int)$id);
    echo "Setup email sent. Link expires in 30 minutes. Account activation status is unchanged.\n";
} catch (Throwable $e) { fwrite(STDERR, "Could not send setup email. Check email uniqueness and SMTP configuration; retry after correcting it.\n"); exit(1); }
