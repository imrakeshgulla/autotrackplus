<?php
require_once __DIR__ . '/../config/config.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
// Exchange URL token for a session-bound value, then remove it from the address bar.
if (isset($_GET['token'])) {
    $_SESSION['password_token'] = is_string($_GET['token']) && preg_match('/^[a-f0-9]{64}$/D', $_GET['token']) ? $_GET['token'] : '';
    header('Location: ' . BASE_URL . '/public/set-password.php'); exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    if (!throttle('consume-ip', $_SERVER['REMOTE_ADDR'] ?? '', 30)) { http_response_code(429); exit('Too many attempts. Try again later.'); }
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmation = is_string($_POST['password_confirmation'] ?? null) ? $_POST['password_confirmation'] : '';
    $error = password_error($password) ?? '';
    if (!$error && $password !== $confirmation) $error = 'Passwords do not match.';
    if (!$error) {
        if (consume_password_link($_SESSION['password_token'] ?? '', $password)) {
            $_SESSION = []; session_regenerate_id(true);
            header('Location: ' . BASE_URL . '/public/login.php?msg=password-set'); exit;
        }
        $error = 'This link is invalid, expired, or already used. Request a new invitation.';
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Choose password • AutoTrackPlus</title><link rel="stylesheet" href="<?= BASE_URL ?>/public/assets/app.css"></head><body><main class="page"><div class="card"><h1>Choose your password</h1><p>Use 15–128 characters. A long, unique passphrase is recommended. Setting a password does not activate a disabled account.</p>
<?php if ($error): ?><p role="alert"><?= h($error) ?></p><?php endif; ?>
<form method="post"><?= csrf_field() ?><label>New password <input class="input" type="password" name="password" autocomplete="new-password" minlength="15" maxlength="128" required></label><label>Confirm password <input class="input" type="password" name="password_confirmation" autocomplete="new-password" minlength="15" maxlength="128" required></label><button class="btn">Save password</button></form><p><a href="<?= BASE_URL ?>/public/request-password.php">Request a new link</a></p></div></main></body></html>
