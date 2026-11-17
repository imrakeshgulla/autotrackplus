<?php
require_once __DIR__ . '/../config/config.php';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $started = microtime(true);
    $code = is_string($_POST['emp_code'] ?? null) ? $_POST['emp_code'] : '';
    $allowedAccount = throttle('setup-account', $code, 3);
    $allowedIp = throttle('setup-ip', $_SERVER['REMOTE_ADDR'] ?? '', 15);
    if ($allowedAccount && $allowedIp && preg_match('/^[0-9]{6}$/D', $code)) {
        $q = $pdo->prepare('SELECT id FROM users WHERE emp_code=? AND password_setup_authorized_at IS NOT NULL AND is_verified=1');
        $q->execute([$code]);
        if ($id = $q->fetchColumn()) {
            try { issue_password_link((int)$id); } catch (Throwable $e) { error_log('Password email delivery failed. Check SMTP.'); }
        }
    }
    // Equalize normal responses so local SMTP timing does not reveal account existence.
    usleep((int)max(0, 750000 - (microtime(true) - $started) * 1000000));
    $message = 'If this account is eligible, a password link will be sent to its authorized email address. Otherwise contact your administrator.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Password help • AutoTrackPlus</title><link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/app.css"></head><body><main class="page"><div class="card"><h1>Set or reset password</h1>
<?php if ($message): ?><p><?= h($message) ?></p><?php else: ?>
<p>Existing users must first have their identity and email confirmed by an administrator.</p>
<form method="post"><?= csrf_field() ?><label>Employee code <input class="input" name="emp_code" pattern="[0-9]{6}" maxlength="6" inputmode="numeric" autocomplete="username" required></label><button class="btn">Email password link</button></form>
<?php endif; ?><p><a href="<?= BASE_URL ?>/public/login.php">Return to login</a></p></div></main></body></html>
