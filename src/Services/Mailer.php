<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

final class Mailer
{
    public function send(array|string $to, string $subject, string $html, ?string $text = null, array $attachments = []): bool
    {
        $recipients = is_array($to) ? $to : [$to];
        $recipients = array_values(array_filter($recipients, static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));
        if ($recipients === []) {
            return false;
        }

        $config = Config::load();
        $driver = (string) $config->get('mail.driver', 'mail');
        $fromEmail = (string) $config->get('mail.from_email', 'noreply@example.com');
        $fromName = (string) $config->get('mail.from_name', 'Fabrika QR');
        $text ??= trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html)));

        foreach ($recipients as $recipient) {
            if ($driver === 'smtp') {
                $this->sendSmtp($recipient, $subject, $html, $text, $attachments, $fromEmail, $fromName);
                continue;
            }

            $this->sendMailFunction($recipient, $subject, $html, $text, $attachments, $fromEmail, $fromName);
        }

        return true;
    }

    private function sendMailFunction(string $recipient, string $subject, string $html, string $text, array $attachments, string $fromEmail, string $fromName): void
    {
        [$body, $contentType] = $this->buildMimeBody($html, $text, $attachments);
        $headers = [
            'MIME-Version: 1.0',
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'Reply-To: ' . $this->formatAddress($fromEmail, $fromName),
            'Content-Type: ' . $contentType,
        ];

        if (!mail($recipient, $this->encodeHeader($subject), $body, implode("\r\n", $headers))) {
            throw new RuntimeException('Mail gonderilemedi: ' . $recipient);
        }
    }

    private function sendSmtp(string $recipient, string $subject, string $html, string $text, array $attachments, string $fromEmail, string $fromName): void
    {
        $config = Config::load();
        $host = (string) $config->get('mail.smtp_host', '');
        $port = (int) $config->get('mail.smtp_port', 587);
        $encryption = (string) $config->get('mail.smtp_encryption', 'tls');
        $prefix = $encryption === 'ssl' ? 'ssl://' : '';
        $socket = stream_socket_client($prefix . $host . ':' . $port, $errno, $errstr, 30);

        if (!$socket) {
            throw new RuntimeException("SMTP baglantisi kurulamadi: {$errstr}");
        }

        $this->smtpExpect($socket, [220]);
        $this->smtpCommand($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);

        if ($encryption === 'tls') {
            $this->smtpCommand($socket, 'STARTTLS', [220]);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->smtpCommand($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), [250]);
        }

        $user = (string) $config->get('mail.smtp_user', '');
        $password = (string) $config->get('mail.smtp_password', '');
        if ($user !== '') {
            $this->smtpCommand($socket, 'AUTH LOGIN', [334]);
            $this->smtpCommand($socket, base64_encode($user), [334]);
            $this->smtpCommand($socket, base64_encode($password), [235]);
        }

        [$body, $contentType] = $this->buildMimeBody($html, $text, $attachments);
        $headers = [
            'From: ' . $this->formatAddress($fromEmail, $fromName),
            'To: <' . $recipient . '>',
            'Subject: ' . $this->encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: ' . $contentType,
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;

        $this->smtpCommand($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        $this->smtpCommand($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        $this->smtpCommand($socket, 'DATA', [354]);
        fwrite($socket, $this->dotStuff($message) . "\r\n.\r\n");
        $this->smtpExpect($socket, [250]);
        $this->smtpCommand($socket, 'QUIT', [221]);
        fclose($socket);
    }

    private function buildMimeBody(string $html, string $text, array $attachments): array
    {
        if ($attachments === []) {
            return [$html, 'text/html; charset=UTF-8'];
        }

        $boundary = 'mixed_' . bin2hex(random_bytes(12));
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($html) . "\r\n";

        foreach ($attachments as $path) {
            if (!is_file($path)) {
                continue;
            }
            $filename = basename($path);
            $body .= "--{$boundary}\r\n";
            $body .= 'Content-Type: application/octet-stream; name="' . addslashes($filename) . '"' . "\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . addslashes($filename) . '"' . "\r\n\r\n";
            $body .= chunk_split(base64_encode((string) file_get_contents($path))) . "\r\n";
        }

        $body .= "--{$boundary}--";

        return [$body, 'multipart/mixed; boundary="' . $boundary . '"'];
    }

    private function smtpCommand($socket, string $command, array $expected): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->smtpExpect($socket, $expected);
    }

    private function smtpExpect($socket, array $expected): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('SMTP hata cevabi: ' . trim($response));
        }

        return $response;
    }

    private function formatAddress(string $email, string $name): string
    {
        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function dotStuff(string $message): string
    {
        return preg_replace('/^\./m', '..', $message) ?? $message;
    }
}

