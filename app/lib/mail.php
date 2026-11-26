<?php
/*
 * Email, in plain text only (light, readable everywhere, nothing to track).
 * Transports (config 'mail.transport'): off, log, mail (PHP mail()), smtp.
 * Emails are queued during the request and sent after the page (see after_response()).
 */

function mail_enabled(): bool
{
    return in_array(config('mail.transport'), ['log', 'mail', 'smtp'], true);
}

function queue_mail(string $to, string $subject, string $body): void
{
    $GLOBALS['agora_mail_queue'][] = [$to, $subject, $body];
}

function flush_mail_queue(): void
{
    $queue = $GLOBALS['agora_mail_queue'] ?? [];
    $GLOBALS['agora_mail_queue'] = [];
    foreach ($queue as [$to, $subject, $body]) {
        try {
            send_mail($to, $subject, $body);
        } catch (Throwable $e) {
            error_log("Email to $to not sent: " . $e->getMessage());
        }
    }
}

/** Sends one email now. */
function send_mail(string $to, string $subject, string $body): void
{
    $to = str_replace(["\r", "\n"], '', $to);
    $subject = str_replace(["\r", "\n"], ' ', $subject);
    if (!valid_email($to) || !mail_enabled()) {
        return;
    }
    $from = (string) config('mail.from');
    $encodedSubject = preg_match('/[^\x20-\x7e]/', $subject)
        ? (function_exists('mb_encode_mimeheader') ? mb_encode_mimeheader($subject, 'UTF-8', 'B') : '=?UTF-8?B?' . base64_encode($subject) . '?=')
        : $subject;
    $headers = [
        'From' => $from,
        'Date' => date('r'),
        'Message-ID' => '<' . bin2hex(random_bytes(12)) . '@' . (parse_url(absolute_url(), PHP_URL_HOST) ?: 'agora') . '>',
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => 'quoted-printable',
        'Auto-Submitted' => 'auto-generated',
    ];
    $encodedBody = quoted_printable_encode(str_replace("\r\n", "\n", $body));

    switch (config('mail.transport')) {
        case 'log':
            $log = dirname((string) config('database')) . '/mail.log';
            file_put_contents($log, "──── " . date('Y-m-d H:i:s') . "\nTo: $to\nSubject: $subject\n\n$body\n\n", FILE_APPEND | LOCK_EX);
            return;
        case 'mail':
            $lines = [];
            foreach ($headers as $k => $v) {
                $lines[] = "$k: $v";
            }
            mail($to, $encodedSubject, $encodedBody, implode("\r\n", $lines));
            return;
        case 'smtp':
            smtp_send($to, ['To' => $to, 'Subject' => $encodedSubject] + $headers, $encodedBody);
            return;
    }
}

/** Address part of "Name <address>". */
function email_address(string $from): string
{
    return preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : trim($from);
}

/**
 * A minimal SMTP client (RFC 5321) with STARTTLS or SSL and AUTH LOGIN.
 * About 40 lines instead of a library: enough to talk to any provider.
 */
function smtp_send(string $to, array $headers, string $body): void
{
    $c = config('mail');
    $secure = (string) ($c['smtp_secure'] ?? 'tls');
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $c['smtp_host'] . ':' . (int) $c['smtp_port'];
    $fp = stream_socket_client($remote, $errno, $error, 15);
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): string {
        $reply = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $reply;
    };
    $say = function (string $command, array $expected) use ($fp, $read): void {
        fwrite($fp, $command . "\r\n");
        $reply = $read();
        if (!in_array((int) substr($reply, 0, 3), $expected, true)) {
            throw new RuntimeException('SMTP: ' . trim($reply));
        }
    };
    $read(); // greeting
    $me = 'EHLO ' . (gethostname() ?: 'agora');
    $say($me, [250]);
    if ($secure === 'tls') {
        $say('STARTTLS', [220]);
        stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $say($me, [250]);
    }
    if (($c['smtp_user'] ?? '') !== '') {
        $say('AUTH LOGIN', [334]);
        $say(base64_encode((string) $c['smtp_user']), [334]);
        $say(base64_encode((string) $c['smtp_password']), [235]);
    }
    $say('MAIL FROM:<' . email_address((string) $c['from']) . '>', [250]);
    $say("RCPT TO:<$to>", [250, 251]);
    $say('DATA', [354]);
    $message = '';
    foreach ($headers as $k => $v) {
        $message .= "$k: $v\r\n";
    }
    $message .= "\r\n" . preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $body));
    $say($message . "\r\n.", [250]);
    $say('QUIT', [221]);
    fclose($fp);
}
