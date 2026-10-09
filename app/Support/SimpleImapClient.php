<?php

namespace App\Support;

class SimpleImapClient
{
    private mixed $stream = null;

    private int $tagNumber = 0;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,
        private readonly string $username,
        private readonly string $password,
        private readonly int $timeout = 30,
    ) {
    }

    public function connect(): void
    {
        $transport = match ($this->encryption) {
            'ssl' => 'ssl',
            'tls' => 'tls',
            default => 'tcp',
        };

        $this->stream = @stream_socket_client(
            "{$transport}://{$this->host}:{$this->port}",
            $errno,
            $error,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );

        if (! $this->stream) {
            throw new \RuntimeException(trim($error) ?: "Unable to connect to IMAP server ({$errno}).");
        }

        stream_set_timeout($this->stream, $this->timeout);
        $greeting = fgets($this->stream);

        if ($greeting === false || ! str_contains($greeting, 'OK')) {
            throw new \RuntimeException('The IMAP server did not accept the connection.');
        }

        $this->command('LOGIN '.$this->quote($this->username).' '.$this->quote($this->password));
    }

    public function messages(string $folder = 'INBOX', int $limit = 50): array
    {
        $this->command('SELECT '.$this->quoteMailbox($folder));
        $searchResponse = $this->command('UID SEARCH ALL');

        if (! preg_match('/^\* SEARCH\s*(.*)$/mi', $searchResponse['raw'], $matches)) {
            return [];
        }

        $uids = collect(preg_split('/\s+/', trim($matches[1])) ?: [])
            ->filter()
            ->map(fn (string $uid) => (int) $uid)
            ->filter(fn (int $uid) => $uid > 0)
            ->reverse()
            ->take($limit)
            ->values();

        return $uids
            ->map(fn (int $uid) => $this->fetchMessage($uid))
            ->filter()
            ->all();
    }

    public function disconnect(): void
    {
        if (! is_resource($this->stream)) {
            return;
        }

        try {
            $this->command('LOGOUT');
        } catch (\Throwable) {
            // The socket is closing anyway.
        }

        fclose($this->stream);
        $this->stream = null;
    }

    private function fetchMessage(int $uid): ?array
    {
        $response = $this->command("UID FETCH {$uid} (UID FLAGS BODY.PEEK[]<0.100000>)");
        $rawMessage = $response['literals'][0] ?? null;

        if (! $rawMessage) {
            return null;
        }

        [$rawHeaders, $rawBody] = $this->splitHeadersAndBody($rawMessage);
        $headers = $this->parseHeaders($rawHeaders);
        $body = $this->extractBody($rawBody, $headers);

        return [
            'uid' => $uid,
            'message_id' => $this->cleanMessageId($headers['message-id'] ?? null),
            'subject' => $this->decodeHeader($headers['subject'] ?? '(No subject)') ?: '(No subject)',
            'from_name' => $this->fromName($headers['from'] ?? null),
            'from_email' => $this->fromEmail($headers['from'] ?? null),
            'body_text' => $body['text'],
            'body_html' => $body['html'],
            'sent_at' => $headers['date'] ?? null,
            'is_seen' => str_contains($response['raw'], '\\Seen'),
            'is_answered' => str_contains($response['raw'], '\\Answered'),
            'has_attachments' => $this->hasAttachments($rawMessage),
        ];
    }

    private function command(string $command): array
    {
        if (! is_resource($this->stream)) {
            throw new \RuntimeException('The IMAP connection is not open.');
        }

        $tag = 'A'.str_pad((string) ++$this->tagNumber, 4, '0', STR_PAD_LEFT);
        fwrite($this->stream, "{$tag} {$command}\r\n");

        $raw = '';
        $literals = [];

        while (($line = fgets($this->stream)) !== false) {
            $raw .= $line;

            if (preg_match('/\{(\d+)\}\r?\n$/', $line, $matches)) {
                $literal = stream_get_contents($this->stream, (int) $matches[1]);
                $literals[] = $literal;
                $raw .= $literal;
                continue;
            }

            if (preg_match('/^'.preg_quote($tag, '/').'\s+(OK|NO|BAD)/i', $line, $matches)) {
                if (strtoupper($matches[1]) !== 'OK') {
                    throw new \RuntimeException(trim($line));
                }

                return ['raw' => $raw, 'literals' => $literals];
            }
        }

        throw new \RuntimeException('The IMAP server closed the connection unexpectedly.');
    }

    private function quote(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function quoteMailbox(string $folder): string
    {
        return $this->quote(str_replace(['..', "\0"], '', $folder));
    }

    private function splitHeadersAndBody(string $message): array
    {
        $parts = preg_split("/\r?\n\r?\n/", $message, 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function parseHeaders(string $rawHeaders): array
    {
        $headers = [];
        $rawHeaders = preg_replace("/\r?\n[ \t]+/", ' ', $rawHeaders) ?? $rawHeaders;

        foreach (preg_split("/\r?\n/", $rawHeaders) ?: [] as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return $headers;
    }

    private function decodeHeader(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return $value;
    }

    private function fromEmail(?string $from): ?string
    {
        if (! $from) {
            return null;
        }

        preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $from, $matches);

        return $matches[0] ?? null;
    }

    private function fromName(?string $from): ?string
    {
        if (! $from) {
            return null;
        }

        $name = preg_replace('/<[^>]+>/', '', $from) ?? '';
        $name = trim($name, " \t\n\r\0\x0B\"'");

        return $this->decodeHeader($name) ?: $this->fromEmail($from);
    }

    private function cleanMessageId(?string $messageId): ?string
    {
        return $messageId ? trim($messageId, " \t\n\r\0\x0B<>") : null;
    }

    private function extractBody(string $rawBody, array $headers): array
    {
        $contentType = strtolower($headers['content-type'] ?? '');

        if (preg_match('/boundary="?([^";]+)"?/i', $contentType, $matches)) {
            return $this->extractMultipartBody($rawBody, $matches[1]);
        }

        $decodedBody = $this->decodeBody($rawBody, $headers);

        if (str_contains($contentType, 'text/html')) {
            return [
                'text' => $this->htmlToText($decodedBody),
                'html' => $this->sanitizeHtml($decodedBody),
            ];
        }

        return [
            'text' => $decodedBody,
            'html' => null,
        ];
    }

    private function extractMultipartBody(string $rawBody, string $boundary): array
    {
        $html = null;
        $text = '';

        foreach (explode('--'.$boundary, $rawBody) as $part) {
            if (trim($part) === '' || str_starts_with(trim($part), '--')) {
                continue;
            }

            [$partHeadersRaw, $partBody] = $this->splitHeadersAndBody($part);
            $partHeaders = $this->parseHeaders($partHeadersRaw);
            $contentType = strtolower($partHeaders['content-type'] ?? '');

            if (str_contains($contentType, 'text/plain')) {
                $text = $this->decodeBody($partBody, $partHeaders);
                continue;
            }

            if ($html === null && str_contains($contentType, 'text/html')) {
                $html = $this->sanitizeHtml($this->decodeBody($partBody, $partHeaders));
            }
        }

        return [
            'text' => $text !== '' ? $text : $this->htmlToText($html ?? ''),
            'html' => $html,
        ];
    }

    private function decodeBody(string $body, array $headers): string
    {
        $encoding = strtolower($headers['content-transfer-encoding'] ?? '');

        if ($encoding === 'quoted-printable') {
            $body = quoted_printable_decode($body);
        } elseif ($encoding === 'base64') {
            $decoded = base64_decode(preg_replace('/\s+/', '', $body) ?? '', true);
            $body = is_string($decoded) ? $decoded : $body;
        }

        if (preg_match('/charset="?([^";]+)"?/i', $headers['content-type'] ?? '', $matches) && function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($body, 'UTF-8', $matches[1]);
            $body = is_string($converted) ? $converted : $body;
        }

        return trim(str_replace(["\r\n", "\r"], "\n", $body));
    }

    private function hasAttachments(string $rawMessage): bool
    {
        return (bool) preg_match('/Content-Disposition:\s*attachment/i', $rawMessage);
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<(br|\/p|\/div|\/tr|\/table)\b[^>]*>/i', "\n", $html) ?? $html;

        return trim(html_entity_decode(strip_tags($html)));
    }

    private function sanitizeHtml(string $html): string
    {
        $html = preg_replace('/<\s*(script|iframe|object|embed|form|input|button)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html) ?? $html;
        $html = preg_replace('/<\s*(script|iframe|object|embed|form|input|button)\b[^>]*\/?\s*>/is', '', $html) ?? $html;
        $html = preg_replace('/\s+on[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;

        return trim($html);
    }
}
