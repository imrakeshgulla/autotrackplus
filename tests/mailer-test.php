<?php
// Standalone transport tests: no database, real API request, or real email.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') === 'smtp-server') {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$server) exit(1);
    echo stream_socket_get_name($server, false), "\n"; flush();
    $client = stream_socket_accept($server, 10);
    if (!$client) exit(1);
    stream_set_timeout($client, 10);
    fwrite($client, "220 test smtp\r\n"); $message = ''; $data = false;
    while (($line = fgets($client)) !== false) {
        if ($data) {
            if ($line === ".\r\n") { $data = false; fwrite($client, "250 accepted\r\n"); }
            else $message .= $line;
        } elseif (str_starts_with($line, 'EHLO ')) fwrite($client, "250 localhost\r\n");
        elseif (str_starts_with($line, 'MAIL FROM:') || str_starts_with($line, 'RCPT TO:')) fwrite($client, "250 ok\r\n");
        elseif (trim($line) === 'DATA') { $data = true; fwrite($client, "354 enter message\r\n"); }
        elseif (trim($line) === 'QUIT') { fwrite($client, "221 bye\r\n"); break; }
        else { fwrite($client, "500 unknown\r\n"); }
    }
    fclose($client); fclose($server);
    echo json_encode(['message' => $message]), "\n";
    exit;
}
require __DIR__ . '/../config/mailer.php';
function check_mail(bool $ok, string $name): void { if (!$ok) throw new RuntimeException($name); echo "PASS $name\n"; }
putenv('BREVO_API_KEY=mock-key-for-unit-testing');
putenv('MAIL_FROM=sender@example.test');
$body = '<p>Choose a password:</p><a href="https://example.test/autotrackplus/public/set-password.php?token=test-token">Set password</a>';
$called = false;
$mock = function ($key, $payload) use (&$called, $body) {
    $called = true; $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    check_mail($key === 'mock-key-for-unit-testing', 'API key passed to transport');
    check_mail($data['sender']['email'] === 'sender@example.test' && $data['sender']['name'] === 'AutoTrackPlus', 'verified sender payload');
    check_mail($data['to'] === [['email'=>'employee@example.test']], 'recipient payload');
    check_mail($data['subject'] === 'Password setup' && $data['htmlContent'] === $body, 'subject and password-link HTML preserved');
    return 201;
};
send_brevo_mail('employee@example.test', 'Password setup', $body, $mock);
check_mail($called, 'successful API response accepted');
foreach ([0, 400, 401, 429, 500] as $status) {
    try { send_brevo_mail('employee@example.test', 'Password setup', $body, fn() => $status); throw new LogicException('Failure accepted'); }
    catch (RuntimeException $e) { check_mail($e->getMessage() === 'Brevo email delivery failed.', "HTTP/connection failure $status is generic"); }
}
putenv('BREVO_API_KEY='); putenv('APP_ENV=production');
try { send_mail('employee@example.test', 'Password setup', $body); throw new LogicException('Missing key accepted'); }
catch (RuntimeException $e) { check_mail($e->getMessage() === 'Brevo API key is missing or invalid.', 'production uses Brevo and rejects missing key without SMTP fallback'); }
putenv("BREVO_API_KEY=invalid\r\nInjected: value");
try { send_brevo_mail('employee@example.test', 'Password setup', $body, fn() => 201); throw new LogicException('Header injection accepted'); }
catch (RuntimeException $e) { check_mail($e->getMessage() === 'Brevo API key is missing or invalid.', 'API key header injection rejected'); }

$process = proc_open([PHP_BINARY, __FILE__, 'smtp-server'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
if (!is_resource($process)) throw new RuntimeException('Could not start SMTP mock');
fclose($pipes[0]);
try {
    $address = trim(fgets($pipes[1]));
    if (!preg_match('/^127\\.0\\.0\\.1:(\\d+)$/', $address, $m)) throw new RuntimeException('SMTP mock failed to start');
    putenv('APP_ENV=local'); putenv('SMTP_HOST=127.0.0.1'); putenv('SMTP_PORT=' . $m[1]);
    putenv('SMTP_TLS'); putenv('SMTP_USER'); putenv('SMTP_PASSWORD');
    send_mail('employee@example.test', 'Password setup', $body);
    $response = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process); $process = null;
    $message = json_decode(trim($response), true, 512, JSON_THROW_ON_ERROR)['message'];
    check_mail($exit === 0 && $errors === '', 'local SMTP exchange succeeds');
    check_mail(str_contains($message, 'To: employee@example.test') && str_contains($message, 'Subject: Password setup') && str_contains($message, $body), 'local SMTP preserves password-link message');
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
}
echo "Mailer tests completed; no email was sent externally.\n";
