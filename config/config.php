<?php
// Environment overrides retain the existing local Docker defaults.
define('BASE_URL', '/autotrackplus');
define('REMINDER_DAYS', 14);
define('APP_ENV', getenv('APP_ENV') ?: 'local');
if (!in_array(APP_ENV, ['local', 'production'], true)) { http_response_code(500); exit('Invalid APP_ENV.'); }
define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost:8080/autotrackplus', '/'));
if (APP_ENV === 'production') {
    foreach (['APP_URL','DB_HOST','DB_NAME','DB_USER','DB_PASSWORD','BREVO_API_KEY','MAIL_FROM'] as $key) {
        if (getenv($key) === false || getenv($key) === '') { http_response_code(500); exit('Missing production configuration: ' . $key); }
    }
    if (!filter_var(APP_URL, FILTER_VALIDATE_URL) || parse_url(APP_URL, PHP_URL_SCHEME) !== 'https' || parse_url(APP_URL, PHP_URL_PATH) !== BASE_URL || preg_match('/[\r\n]/', getenv('BREVO_API_KEY')) || !filter_var(getenv('MAIL_FROM'), FILTER_VALIDATE_EMAIL)) { http_response_code(500); exit('Invalid production HTTPS or email configuration.'); }
}
try {
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
    if ($ca = getenv('DB_SSL_CA')) { $options[PDO::MYSQL_ATTR_SSL_CA] = $ca; $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true; }
    $pdo = new PDO('mysql:host=' . (getenv('DB_HOST') ?: 'db') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . (getenv('DB_NAME') ?: 'autotrackplus') . ';charset=utf8mb4', getenv('DB_USER') ?: 'root', getenv('DB_PASSWORD') ?: '', $options);
    $pdo->exec("SET time_zone = '+00:00'");
} catch (Exception $e) {
    http_response_code(500);
    exit('Database unavailable. Check MySQL and the application configuration.');
}
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['httponly' => true, 'secure' => str_starts_with(APP_URL, 'https://'), 'samesite' => 'Lax', 'path' => BASE_URL . '/']);
    if (APP_ENV === 'production' || getenv('SESSION_DRIVER') === 'mysql') {
        require_once __DIR__ . '/session_handler.php';
        session_set_save_handler(new MysqlSessionHandler($pdo), true);
    }
    ini_set('session.gc_maxlifetime', '43200');
    session_start();
}
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/mailer.php';
