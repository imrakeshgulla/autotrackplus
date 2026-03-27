<?php
require_once __DIR__ . '/../config/config.php';

$fn = trim($_POST['first_name'] ?? '');
$ln = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$code = preg_replace('/\D/','', $_POST['emp_code'] ?? '');

if (!$fn || !$ln || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($code)!==6) {
  header('Location: ' . BASE_URL . '/public/register.php?err=Invalid input'); exit;
}

try {
  $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email=? OR emp_code=? OR (first_name=? AND last_name=? AND emp_code=?)");
  $stmt->execute([$email,$code,$fn,$ln,$code]);
  if ($stmt->fetch()) { header('Location: ' . BASE_URL . '/public/register.php?err=Already registered'); exit; }

  $token = bin2hex(random_bytes(16));
  $ins = $pdo->prepare("INSERT INTO users(first_name,last_name,email,emp_code,verify_token) VALUES (?,?,?,?,?)");
  $ins->execute([$fn,$ln,$email,$code,$token]);

  $link = sprintf('%s://%s%s/public/verify.php?token=%s', isset($_SERVER['HTTPS'])?'https':'http', $_SERVER['HTTP_HOST'], BASE_URL, $token);
  $body = "<p>Hello $fn,</p><p>Verify your AutoTrack+ account by clicking:</p><p><a href='$link'>$link</a></p>";
  send_mail($email, 'Verify your AutoTrack+ account', $body);

  header('Location: ' . BASE_URL . '/public/login.php?msg=checkemail'); exit;

} catch(Exception $e) {
  header('Location: ' . BASE_URL . '/public/register.php?err=Server error'); exit;
}
