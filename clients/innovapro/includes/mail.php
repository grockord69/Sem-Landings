<?php
declare(strict_types=1);

function smtp_is_configured(): bool
{
    return (bool) cfg('smtp.host')
        && (bool) cfg('smtp.username')
        && (bool) cfg('smtp.password')
        && (bool) cfg('smtp.from_email')
        && is_array(cfg('smtp.recipients'))
        && count(cfg('smtp.recipients')) > 0;
}

function mail_header_value(string $value): string
{
    return trim(str_replace(["\r", "\n"], '', $value));
}

function mime_header(string $value): string
{
    $value = mail_header_value($value);
    return preg_match('/[^\x20-\x7E]/', $value)
        ? '=?UTF-8?B?' . base64_encode($value) . '?='
        : $value;
}

function smtp_read_response($socket): array
{
    $lines = [];
    $code = 0;

    while (($line = fgets($socket, 8192)) !== false) {
        $lines[] = rtrim($line, "\r\n");
        if (preg_match('/^(\d{3})([ -])/', $line, $match)) {
            $code = (int) $match[1];
            if ($match[2] === ' ') {
                break;
            }
        }
    }

    if (!$lines) {
        throw new RuntimeException('SMTP_CONNECTION_CLOSED');
    }

    return [$code, implode("\n", $lines)];
}

function smtp_expect($socket, array $expected): string
{
    [$code, $response] = smtp_read_response($socket);
    if (!in_array($code, $expected, true)) {
        throw new RuntimeException('SMTP_UNEXPECTED_RESPONSE_' . $code);
    }
    return $response;
}

function smtp_write_all($socket, string $data): void
{
    $length = strlen($data);
    $offset = 0;

    while ($offset < $length) {
        $written = fwrite($socket, substr($data, $offset));
        if ($written === false || $written === 0) {
            throw new RuntimeException('SMTP_WRITE_FAILED');
        }
        $offset += $written;
    }
}

function smtp_command($socket, string $command, array $expected): string
{
    smtp_write_all($socket, $command . "\r\n");
    return smtp_expect($socket, $expected);
}

function smtp_message(array $lead, array $recipients): string
{
    $fromEmail = mail_header_value((string) cfg('smtp.from_email'));
    $fromName = mime_header((string) cfg('smtp.from_name', 'InnovaPro'));
    $replyEmail = mail_header_value($lead['email']);
    $replyName = mime_header($lead['nombre']);
    $subject = mime_header('Nueva solicitud · InnovaPro · Lead #' . $lead['id']);
    $domain = str_contains($fromEmail, '@') ? substr(strrchr($fromEmail, '@'), 1) : 'localhost';
    $boundary = '=_innovapro_' . bin2hex(random_bytes(12));

    $rows = '';
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'wbraid', 'gbraid', 'landing_url', 'referrer'] as $key) {
        if (!empty($lead[$key])) {
            $rows .= '<tr><th style="text-align:left;padding:6px 12px 6px 0">' . esc($key) . '</th><td>' . esc($lead[$key]) . '</td></tr>';
        }
    }

    $html = '<h2>Nueva solicitud de depilación profesional · InnovaPro</h2>'
        . '<p><strong>ID interno:</strong> ' . esc($lead['id']) . '<br>'
        . '<strong>Fecha y hora:</strong> ' . esc(local_date($lead['created_at'])) . '<br>'
        . '<strong>Nombre:</strong> ' . esc($lead['nombre']) . '<br>'
        . '<strong>Teléfono:</strong> ' . esc($lead['telefono']) . '<br>'
        . '<strong>Email:</strong> ' . esc($lead['email']) . '</p>'
        . '<p>Interés: información o demostración; compra, alquiler y financiación.</p>'
        . ($rows ? '<table>' . $rows . '</table>' : '')
        . '<p>La solicitud está guardada en el panel privado.</p>';

    $text = "Nueva solicitud InnovaPro\r\n"
        . 'ID: ' . $lead['id'] . "\r\n"
        . 'Fecha: ' . local_date($lead['created_at']) . "\r\n"
        . 'Nombre: ' . $lead['nombre'] . "\r\n"
        . 'Teléfono: ' . $lead['telefono'] . "\r\n"
        . 'Email: ' . $lead['email'];

    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . mail_header_value($domain) . '>',
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'To: ' . implode(', ', array_map('mail_header_value', $recipients)),
        'Reply-To: ' . $replyName . ' <' . $replyEmail . '>',
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

    return implode("\r\n", $headers) . "\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text), 76, "\r\n") . "\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html), 76, "\r\n") . "\r\n"
        . '--' . $boundary . "--\r\n";
}

/**
 * Cliente SMTP mínimo y deliberadamente limitado a autenticación LOGIN,
 * STARTTLS (tls) o TLS implícito (ssl). No depende de Composer ni mail().
 */
function send_lead_email(array $lead): void
{
    if (!smtp_is_configured()) {
        throw new RuntimeException('SMTP_NOT_CONFIGURED');
    }

    $host = (string) cfg('smtp.host');
    $port = (int) cfg('smtp.port', 587);
    $encryption = (string) cfg('smtp.encryption', 'tls');
    $timeout = max(2, min(12, (int) cfg('smtp.timeout_seconds', 6)));
    $fromEmail = (string) cfg('smtp.from_email');
    $recipients = array_values(array_filter((array) cfg('smtp.recipients', []), static fn($email): bool => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)));

    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || !$recipients) {
        throw new RuntimeException('SMTP_INVALID_ADDRESS');
    }

    $ssl = [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'peer_name' => $host,
        'SNI_enabled' => true,
        'disable_compression' => true,
    ];
    $context = stream_context_create(['ssl' => $ssl]);
    $transport = $encryption === 'ssl' ? 'ssl' : 'tcp';
    $socket = @stream_socket_client(
        $transport . '://' . $host . ':' . $port,
        $errno,
        $error,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!is_resource($socket)) {
        throw new RuntimeException('SMTP_CONNECT_FAILED');
    }

    stream_set_timeout($socket, $timeout);

    try {
        smtp_expect($socket, [220]);
        $hostname = gethostname() ?: 'localhost';
        smtp_command($socket, 'EHLO ' . preg_replace('/[^a-zA-Z0-9.-]/', '', $hostname), [250]);

        if ($encryption === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP_TLS_FAILED');
            }
            smtp_command($socket, 'EHLO ' . preg_replace('/[^a-zA-Z0-9.-]/', '', $hostname), [250]);
        }

        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode((string) cfg('smtp.username')), [334]);
        smtp_command($socket, base64_encode((string) cfg('smtp.password')), [235]);
        smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);

        foreach ($recipients as $recipient) {
            smtp_command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        }

        smtp_command($socket, 'DATA', [354]);
        $message = preg_replace('/(?m)^\./', '..', smtp_message($lead, $recipients));
        smtp_write_all($socket, $message . "\r\n.\r\n");
        smtp_expect($socket, [250]);
        smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}
