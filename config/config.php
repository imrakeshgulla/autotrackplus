<?php
// config/config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------- App base URL (folder under htdocs) ---------- */
if (!defined('BASE_URL'))      define('BASE_URL', '/autotrackplus');
if (!defined('REMINDER_DAYS')) define('REMINDER_DAYS', 14);  // FIX: was nested inside BASE_URL block

/* ------------------ Database settings ------------------- */
$DB_HOST = '127.0.0.1';
$DB_PORT = '3306';
$DB_NAME = 'autotrackplus';   // FIX: was 'autotrack' in schema, now consistent
$DB_USER = 'root';
$DB_PASS = '';                 // set if your root user has a password

try {
  $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset=utf8mb4";
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Exception $e) {
  http_response_code(500);
  exit('DB connection failed. Make sure MySQL is running and the database exists.');
}

/* -------------------- Auth helper ----------------------- */
function require_auth() {
  if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . '/public/login.php');
    exit;
  }
}

/* ------------------- Simple mailer ---------------------- */
function send_mail($to, $subject, $body) {
  $headers  = "MIME-Version: 1.0\r\n";
  $headers .= "Content-type:text/html;charset=UTF-8\r\n";
  $headers .= "From: AutoTrack+ <no-reply@autotrack.local>\r\n";
  @mail($to, $subject, $body, $headers);
}
