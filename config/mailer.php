<?php
// SMTP transport: local Mailpit by default in Compose; TLS and credentials configurable.
function send_mail(string $to, string $subject, string $body): void {
    $host = getenv('SMTP_HOST');
    if (!$host) throw new RuntimeException('SMTP_HOST is not configured.');
    $port = (int)(getenv('SMTP_PORT') ?: 1025);
    $from = getenv('MAIL_FROM') ?: 'no-reply@autotrack.local';
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to . $from . $subject)) throw new RuntimeException('Invalid mail headers.');
    $socket = stream_socket_client('tcp://' . $host . ':' . $port, $errno, $error, 10);
    if (!$socket) throw new RuntimeException('SMTP connection failed.');
    stream_set_timeout($socket, 10);
    $read = static function(array $codes) use ($socket): void {
        do { $line = fgets($socket, 2048); if ($line === false) throw new RuntimeException('SMTP response missing.'); } while (isset($line[3]) && $line[3] === '-');
        if (!in_array((int)substr($line, 0, 3), $codes, true)) throw new RuntimeException('SMTP request rejected.');
    };
    $command = static function(string $command, array $codes) use ($socket, $read): void { fwrite($socket, $command . "\r\n"); $read($codes); };
    try {
        $read([220]); $command('EHLO autotrackplus', [250]);
        $tls = getenv('SMTP_TLS') === '1';
        if ($tls) {
            $command('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('SMTP TLS failed.');
            $command('EHLO autotrackplus', [250]);
        }
        if ($username = getenv('SMTP_USER')) {
            if (!$tls) throw new RuntimeException('SMTP credentials require TLS.');
            $command('AUTH LOGIN', [334]); $command(base64_encode($username), [334]); $command(base64_encode(getenv('SMTP_PASSWORD') ?: ''), [235]);
        }
        $command('MAIL FROM:<' . $from . '>', [250]); $command('RCPT TO:<' . $to . '>', [250,251]); $command('DATA', [354]);
        $message = 'From: AutoTrackPlus <' . $from . ">\r\nTo: " . $to . "\r\nSubject: " . $subject . "\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $body;
        $message = preg_replace('/\r?\n/', "\r\n", $message);
        $message = preg_replace('/^\./m', '..', $message);
        fwrite($socket, $message . "\r\n.\r\n"); $read([250]);
        // A message acknowledged with 250 has been accepted even if QUIT fails.
        try { $command('QUIT', [221]); } catch (RuntimeException $e) { /* already accepted */ }
    } finally { fclose($socket); }
}
