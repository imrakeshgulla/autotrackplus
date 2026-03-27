<?php
require_once __DIR__ . '/../config/config.php'; require_auth();
if ($_SESSION['user']['role'] !== 'admin'){ header('Location: ' . BASE_URL . '/public/login.php'); exit; }

$fn = trim($_POST['first_name'] ?? '');
$ln = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$code = preg_replace('/\D/','', $_POST['emp_code'] ?? '');
$role = ($_POST['role'] ?? 'employee') === 'admin' ? 'admin' : 'employee';

if (!$fn || !$ln || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($code)!==6) {
  header('Location: ' . BASE_URL . '/public/admin/index.php'); exit;
}

try {
  // prevent duplicates
  $q = $pdo->prepare("SELECT 1 FROM users WHERE email=? OR emp_code=? OR (first_name=? AND last_name=? AND emp_code=?)");
  $q->execute([$email,$code,$fn,$ln,$code]);
  if ($q->fetch()){ header('Location: ' . BASE_URL . '/public/admin/index.php'); exit; }

  $token = bin2hex(random_bytes(16));
  $pdo->prepare("INSERT INTO users(first_name,last_name,email,emp_code,role,is_verified,verify_token) 
                 VALUES (?,?,?,?,?,1,?)")
      ->execute([$fn,$ln,$email,$code,$role,$token]);
} catch(Exception $e){ /* ignore */ }

header('Location: ' . BASE_URL . '/public/admin/index.php');
