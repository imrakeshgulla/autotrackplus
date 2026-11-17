<?php
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function csrf_token(): string {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function require_post_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit('POST required.'); }
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals(csrf_token(), $_POST['csrf'])) { http_response_code(403); exit('Invalid form token. Reload the form and try again.'); }
}
function require_auth(): void {
    global $pdo;
    $session = $_SESSION['user'] ?? null;
    if ($session && isset($session['session_version'])) {
        $q = $pdo->prepare('SELECT role, is_verified, password_hash, session_version FROM users WHERE id=?');
        $q->execute([$session['id']]);
        $u = $q->fetch();
        if ($u && $u['is_verified'] && $u['password_hash'] && (int)$u['session_version'] === (int)$session['session_version'] && $u['role'] === $session['role']) {
            $last = $_SESSION['last_seen'] ?? 0;
            if (time() - $last <= 1800 && time() - ($_SESSION['authenticated_at'] ?? 0) <= 43200) {
                $_SESSION['last_seen'] = time();
                return;
            }
        }
    }
    unset($_SESSION['user']);
    header('Location: ' . BASE_URL . '/public/login.php'); exit;
}
function require_admin(): void {
    require_auth();
    if ($_SESSION['user']['role'] !== 'admin') { http_response_code(403); exit('Administrator access required.'); }
}
// Each attempt increments both account and network buckets; cookies cannot bypass this.
function throttle(string $scope, string $identity, int $limit, int $seconds = 900): bool {
    global $pdo;
    $key = hash('sha256', $scope . ':' . $identity);
    $pdo->prepare('INSERT INTO auth_rate_limits (bucket, attempts, expires_at) VALUES (?,1,DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND)) ON DUPLICATE KEY UPDATE attempts=IF(expires_at<=UTC_TIMESTAMP(),1,attempts+1), expires_at=IF(expires_at<=UTC_TIMESTAMP(),VALUES(expires_at),expires_at)')->execute([$key, $seconds]);
    $q = $pdo->prepare('SELECT attempts FROM auth_rate_limits WHERE bucket=?');
    $q->execute([$key]);
    return (int)$q->fetchColumn() <= $limit;
}
function password_error(string $password): ?string {
    if (preg_match('//u', $password) !== 1) return 'Use a valid UTF-8 password.';
    $length = preg_match_all('/./us', $password);
    if ($length < 15 || $length > 128 || strlen($password) > 512) return 'Use a password of 15 to 128 characters.';
    $common = ['passwordpassword', 'password123456789', '123456789012345', 'qwertyuiopasdfgh', 'autotrackplus123', 'letmeinletmein123'];
    if (in_array(strtolower($password), $common, true) || preg_match('/^(.)\1+$/us', $password)) return 'Choose a less predictable password.';
    return null;
}
function issue_password_link(int $userId): void {
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $pdo->beginTransaction();
    try {
        // Serialize invitation issuance and consumption for this user.
        $q = $pdo->prepare('SELECT email, password_setup_authorized_at FROM users WHERE id=? FOR UPDATE');
        $q->execute([$userId]); $user = $q->fetch();
        if (!$user || !$user['password_setup_authorized_at']) throw new RuntimeException('Account email must first be authorized by an administrator or operator.');
        $pdo->prepare('DELETE FROM password_tokens WHERE user_id=?')->execute([$userId]);
        $pdo->prepare("INSERT INTO password_tokens (user_id, token_hash, purpose, expires_at) VALUES (?,?,'password',DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 MINUTE))")->execute([$userId, hash('sha256', $token)]);
        $pdo->commit();
        $link = APP_URL . '/public/set-password.php?token=' . $token;
        send_mail($user['email'], 'Set your AutoTrackPlus password', '<p>Set or reset your AutoTrackPlus password using this single-use link (expires in 30 minutes):</p><p><a href="' . h($link) . '">' . h($link) . '</a></p><p>If you did not expect this email, contact your administrator.</p>');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Never leave an undelivered token usable.
        $pdo->prepare('DELETE FROM password_tokens WHERE token_hash=?')->execute([hash('sha256', $token)]);
        throw $e;
    }
}
function consume_password_link(string $token, string $password): bool {
    global $pdo;
    if (!preg_match('/^[a-f0-9]{64}$/D', $token) || password_error($password)) return false;
    $hash = password_hash($password, PASSWORD_ARGON2ID);
    $lookup = $pdo->prepare('SELECT user_id FROM password_tokens WHERE token_hash=?');
    $lookup->execute([hash('sha256', $token)]);
    $id = $lookup->fetchColumn();
    if (!$id) return false;
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE'); $q->execute([$id]);
        $q = $pdo->prepare("SELECT id FROM password_tokens WHERE user_id=? AND token_hash=? AND purpose='password' AND consumed_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE");
        $q->execute([$id, hash('sha256', $token)]);
        if (!$q->fetch()) { $pdo->rollBack(); return false; }
        $update = $pdo->prepare('UPDATE users SET password_hash=?, password_changed_at=UTC_TIMESTAMP(), session_version=session_version+1, verify_token=NULL WHERE id=? AND password_setup_authorized_at IS NOT NULL');
        $update->execute([$hash, $id]);
        if ($update->rowCount() !== 1) { $pdo->rollBack(); return false; }
        $pdo->prepare('UPDATE password_tokens SET consumed_at=UTC_TIMESTAMP() WHERE user_id=? AND consumed_at IS NULL')->execute([$id]);
        $pdo->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, 'Password set or reset')")->execute([$id]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
