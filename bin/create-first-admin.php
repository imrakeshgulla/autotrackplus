<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/config.php';
$o = getopt('', ['code:', 'email:', 'first-name:', 'last-name:', 'confirm-identity']);
$code = $o['code'] ?? ''; $email = $o['email'] ?? '';
$first = trim($o['first-name'] ?? ''); $last = trim($o['last-name'] ?? '');
if (!preg_match('/^[0-9]{6}$/D', $code) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$first || !$last || strlen($first)>80 || strlen($last)>80 || strlen($email)>160 || !isset($o['confirm-identity'])) {
    fwrite(STDERR, "Usage: php bin/create-first-admin.php --code=123456 --email=confirmed@example.com --first-name=First --last-name=Last --confirm-identity\n"); exit(1);
}
if (!$pdo->query("SELECT GET_LOCK('autotrackplus_first_admin',10)")->fetchColumn()) exit("Could not acquire bootstrap lock.\n");
try {
    if ((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() !== 0) { fwrite(STDERR, "An admin already exists; use the existing admin or issue-password-setup.php.\n"); exit(1); }
    $pdo->prepare("INSERT INTO users (first_name,last_name,email,emp_code,role,is_verified,password_setup_authorized_at) VALUES (?,?,?,?,'admin',1,UTC_TIMESTAMP())")->execute([$first,$last,$email,$code]);
    $id = (int)$pdo->lastInsertId();
    try { issue_password_link($id); echo "Admin created; setup email sent.\n"; }
    catch (Throwable $e) { fwrite(STDERR, "Admin created but email delivery failed. Retry using bin/issue-password-setup.php.\n"); exit(1); }
} finally { $pdo->query("SELECT RELEASE_LOCK('autotrackplus_first_admin')"); }
