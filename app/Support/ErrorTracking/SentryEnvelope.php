<?php

namespace App\Support\ErrorTracking;

/**
 * A read-only view of a Sentry envelope: newline-delimited JSON, one header line, then pairs of an item header
 * line and a payload. A payload runs to the next newline unless its item header gives a byte `length`, which
 * may then include newlines. The envelope is only read, never rebuilt: the tunnel forwards the original bytes.
 */
final readonly class SentryEnvelope
{
    /**
     * @param  list<string>  $itemTypes
     */
    private function __construct(
        public ?string $dsn,
        public array $itemTypes,
    ) {}

    /**
     * Null when the body is not a well-formed envelope.
     */
    public static function parse(string $body): ?self
    {
        $headerEnd = strpos($body, "\n");
        $header = json_decode($headerEnd === false ? $body : substr($body, 0, $headerEnd), true);

        if (! is_array($header)) {
            return null;
        }

        $dsn = $header['dsn'] ?? null;
        $itemTypes = [];
        $offset = $headerEnd === false ? strlen($body) : $headerEnd + 1;
        $size = strlen($body);

        while ($offset < $size) {
            $lineEnd = strpos($body, "\n", $offset);
            $line = substr($body, $offset, ($lineEnd === false ? $size : $lineEnd) - $offset);
            $offset = $lineEnd === false ? $size : $lineEnd + 1;

            if ($line === '') {
                continue;
            }

            $itemHeader = json_decode($line, true);

            if (! is_array($itemHeader) || ! is_string($itemHeader['type'] ?? null)) {
                return null;
            }

            $itemTypes[] = $itemHeader['type'];
            $offset = self::skipPayload($body, $offset, $itemHeader['length'] ?? null);
        }

        return new self(is_string($dsn) ? $dsn : null, $itemTypes);
    }

    private static function skipPayload(string $body, int $offset, mixed $length): int
    {
        $size = strlen($body);

        if (is_int($length) && $length >= 0) {
            return min($size, $offset + $length + 1);
        }

        $payloadEnd = strpos($body, "\n", $offset);

        return $payloadEnd === false ? $size : $payloadEnd + 1;
    }
}
