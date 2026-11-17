<?php
require_once __DIR__ . '/../config/config.php'; require_admin(); require_post_csrf();
$fn = trim($_POST['first_name'] ?? ''); $ln = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? ''); $code = $_POST['emp_code'] ?? '';
$role = ($_POST['role'] ?? 'employee') === 'admin' ? 'admin' : 'employee';
$message = 'Invalid input. Confirm employee identity and email before inviting.';
if ($fn && $ln && strlen($fn)<=80 && strlen($ln)<=80 && strlen($email)<=160 && filter_var($email,FILTER_VALIDATE_EMAIL) && preg_match('/^[0-9]{6}$/D', $code) && isset($_POST['confirm_identity'])) {
    try {
        $pdo->prepare('INSERT INTO users (first_name,last_name,email,emp_code,role,is_verified,password_setup_authorized_at) VALUES (?,?,?,?,?,1,UTC_TIMESTAMP())')->execute([$fn,$ln,$email,$code,$role]);
        $id = (int)$pdo->lastInsertId();
        $message = 'Account created. Invitation delivery failed; use Send password invitation to retry.';
        try { issue_password_link($id); $message = 'Account created and password invitation sent.'; } catch (Throwable $e) { error_log('Invitation delivery failed.'); }
    } catch (PDOException $e) { $message = 'Could not create account. Check for a duplicate employee code or email.'; }
}
$_SESSION['admin_message'] = $message;
header('Location: ' . BASE_URL . '/public/admin/index.php'); exit;
