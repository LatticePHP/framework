<?php

declare(strict_types=1);

namespace Lattice\Mail\Transport;

use Lattice\Mail\Mailable;
use RuntimeException;

final class SmtpTransport implements MailTransportInterface
{
    public function __construct(
        private readonly SmtpConfig $config,
    ) {}

    public function send(Mailable $mailable): void
    {
        $mailable->build();

        $socket = $this->connect();

        try {
            $this->expectCode($socket, 220);
            $this->command($socket, "EHLO {$this->config->host}", 250);

            if ($this->config->encryption === 'tls') {
                $this->command($socket, 'STARTTLS', 220);
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->command($socket, "EHLO {$this->config->host}", 250);
            }

            if ($this->config->username !== null && $this->config->password !== null) {
                $this->command($socket, 'AUTH LOGIN', 334);
                $this->command($socket, base64_encode($this->config->username), 334);
                $this->command($socket, base64_encode($this->config->password), 235);
            }

            $from = $mailable->getFrom();
            $this->command($socket, "MAIL FROM:<{$from['address']}>", 250);

            foreach ($mailable->getTo() as $recipient) {
                $this->command($socket, "RCPT TO:<{$recipient}>", 250);
            }

            foreach ($mailable->getCc() as $recipient) {
                $this->command($socket, "RCPT TO:<{$recipient}>", 250);
            }

            foreach ($mailable->getBcc() as $recipient) {
                $this->command($socket, "RCPT TO:<{$recipient}>", 250);
            }

            $this->command($socket, 'DATA', 354);

            $headers = $this->buildHeaders($mailable);
            $body = $mailable->getHtml() !== '' ? $mailable->getHtml() : $mailable->getText();

            fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
            $this->expectCode($socket, 250);

            $this->command($socket, 'QUIT', 221);
        } finally {
            fclose($socket);
        }
    }

    /** @return resource */
    private function connect(): mixed
    {
        $protocol = $this->config->encryption === 'ssl' ? 'ssl' : 'tcp';
        $address = "{$protocol}://{$this->config->host}:{$this->config->port}";

        $socket = @stream_socket_client(
            $address,
            $errorCode,
            $errorMessage,
            30,
        );

        if ($socket === false) {
            throw new RuntimeException("Could not connect to SMTP server: {$errorMessage} ({$errorCode})");
        }

        return $socket;
    }

    /** @param resource $socket */
    private function command(mixed $socket, string $command, int $expectedCode): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expectCode($socket, $expectedCode);
    }

    /** @param resource $socket */
    private function expectCode(mixed $socket, int $expectedCode): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new RuntimeException("SMTP error: expected {$expectedCode}, got {$code}. Response: {$response}");
        }

        return $response;
    }

    private function buildHeaders(Mailable $mailable): string
    {
        $from = $mailable->getFrom();
        $fromHeader = $from['name'] !== null
            ? "{$from['name']} <{$from['address']}>"
            : $from['address'];

        $headers = "From: {$fromHeader}\r\n";
        $headers .= 'To: ' . implode(', ', $mailable->getTo()) . "\r\n";

        if ($mailable->getCc() !== []) {
            $headers .= 'Cc: ' . implode(', ', $mailable->getCc()) . "\r\n";
        }

        $headers .= "Subject: {$mailable->getSubject()}\r\n";

        if ($mailable->getReplyTo() !== null) {
            $headers .= "Reply-To: {$mailable->getReplyTo()}\r\n";
        }

        $headers .= "MIME-Version: 1.0\r\n";

        if ($mailable->getHtml() !== '') {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }

        return $headers;
    }
}
