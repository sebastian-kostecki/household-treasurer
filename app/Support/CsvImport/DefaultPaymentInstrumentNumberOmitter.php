<?php

namespace App\Support\CsvImport;

final class DefaultPaymentInstrumentNumberOmitter implements PaymentInstrumentNumberOmitter
{
    public function omit(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim($text);

        if ($text === '') {
            return null;
        }

        $spans = $this->collectRemovalSpans($text);
        $result = $this->removeSpans($text, $spans);
        $result = $this->collapseWhitespace($result);

        return $result === '' ? null : $result;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function collectRemovalSpans(string $text): array
    {
        $spans = [];

        foreach ($this->findPolishNrbSpans($text) as $span) {
            $this->addSpan($spans, $span);
        }

        foreach ($this->findIbanSpans($text) as $span) {
            $this->addSpan($spans, $span);
        }

        foreach ($this->findMaskedPanSpans($text) as $span) {
            $this->addSpan($spans, $span);
        }

        foreach ($this->findUnmaskedPanSpans($text, $spans) as $span) {
            $this->addSpan($spans, $span);
        }

        return $spans;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $spans
     */
    private function addSpan(array &$spans, array $span): void
    {
        foreach ($spans as $existing) {
            if ($this->spansOverlap($existing, $span)) {
                return;
            }
        }

        $spans[] = $span;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function findPolishNrbSpans(string $text): array
    {
        $spans = [];
        $length = mb_strlen($text);

        for ($offset = 0; $offset < $length; $offset++) {
            $span = $this->matchPolishNrbAt($text, $offset);

            if ($span === null) {
                continue;
            }

            $spans[] = $span;
            $offset = $span[1] - 1;
        }

        return $spans;
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function matchPolishNrbAt(string $text, int $offset): ?array
    {
        $length = mb_strlen($text);
        $start = $offset;
        $position = $offset;
        $hasPlPrefix = false;

        if ($position + 2 <= $length && mb_strtoupper(mb_substr($text, $position, 2)) === 'PL') {
            $hasPlPrefix = true;
            $position += 2;

            while ($position < $length && mb_substr($text, $position, 1) === ' ') {
                $position++;
            }
        } elseif (! $this->isAsciiDigit(mb_substr($text, $position, 1))) {
            return null;
        }

        $digits = 0;
        $end = $position;

        while ($end < $length && $digits < 26) {
            $character = mb_substr($text, $end, 1);

            if ($this->isAsciiDigit($character)) {
                $digits++;
                $end++;
            } elseif ($character === ' ' && $digits > 0) {
                $end++;
            } else {
                break;
            }
        }

        if ($digits !== 26) {
            return null;
        }

        if (! $hasPlPrefix) {
            if ($start > 0 && $this->isAsciiDigit(mb_substr($text, $start - 1, 1))) {
                return null;
            }

            if ($end < $length && $this->isAsciiDigit(mb_substr($text, $end, 1))) {
                return null;
            }
        }

        return [$start, $end];
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function findIbanSpans(string $text): array
    {
        $spans = [];

        if (preg_match_all('/(?<![A-Z0-9])[A-Z]{2}\s*\d{2}(?:\s*[A-Z0-9])+/i', $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return $spans;
        }

        foreach ($matches[0] as [$match, $byteOffset]) {
            $start = mb_strlen(substr($text, 0, $byteOffset));
            $end = $start + mb_strlen($match);

            if ($this->isValidIban($match)) {
                $spans[] = [$start, $end];
            }
        }

        return $spans;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function findMaskedPanSpans(string $text): array
    {
        $spans = [];

        if (preg_match_all('/\d{6,}[-_]{4,}\d{4}/', $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return $spans;
        }

        foreach ($matches[0] as [$match, $byteOffset]) {
            $start = mb_strlen(substr($text, 0, $byteOffset));
            $spans[] = [$start, $start + mb_strlen($match)];
        }

        return $spans;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $existingSpans
     * @return list<array{0: int, 1: int}>
     */
    private function findUnmaskedPanSpans(string $text, array $existingSpans): array
    {
        $spans = [];
        $length = mb_strlen($text);

        for ($offset = 0; $offset < $length; $offset++) {
            if (! $this->isAsciiDigit(mb_substr($text, $offset, 1))) {
                continue;
            }

            $end = $offset;

            while ($end < $length && $this->isAsciiDigit(mb_substr($text, $end, 1))) {
                $end++;
            }

            $digitCount = $end - $offset;

            if ($digitCount < 13 || $digitCount > 19) {
                $offset = $end - 1;

                continue;
            }

            $candidateSpan = [$offset, $end];

            if ($this->spanOverlapsAny($candidateSpan, $existingSpans)) {
                $offset = $end - 1;

                continue;
            }

            $digits = mb_substr($text, $offset, $digitCount);

            if ($this->passesLuhn($digits)) {
                $spans[] = $candidateSpan;
            }

            $offset = $end - 1;
        }

        return $spans;
    }

    private function isValidIban(string $candidate): bool
    {
        $compact = strtoupper(preg_replace('/\s+/', '', $candidate) ?? $candidate);
        $length = strlen($compact);

        if ($length < 15 || $length > 34) {
            return false;
        }

        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]+$/', $compact)) {
            return false;
        }

        $rearranged = substr($compact, 4).substr($compact, 0, 4);
        $remainder = 0;

        foreach (str_split($rearranged) as $character) {
            $chunk = ctype_alpha($character)
                ? (string) (ord($character) - 55)
                : $character;

            foreach (str_split($chunk) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }

    private function passesLuhn(string $digits): bool
    {
        if (! preg_match('/^\d+$/', $digits)) {
            return false;
        }

        $sum = 0;
        $alternate = false;

        for ($index = strlen($digits) - 1; $index >= 0; $index--) {
            $digit = (int) $digits[$index];

            if ($alternate) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $alternate = ! $alternate;
        }

        return $sum % 10 === 0;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $spans
     */
    private function removeSpans(string $text, array $spans): string
    {
        usort($spans, static fn (array $left, array $right): int => $right[0] <=> $left[0]);

        foreach ($spans as [$start, $end]) {
            $text = mb_substr($text, 0, $start).mb_substr($text, $end);
        }

        return $text;
    }

    private function collapseWhitespace(string $text): string
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $collapsedLines = [];

        foreach ($lines as $line) {
            $line = preg_replace('/\s+/u', ' ', trim($line)) ?? trim($line);

            if ($line !== '') {
                $collapsedLines[] = $line;
            }
        }

        return implode("\n", $collapsedLines);
    }

    /**
     * @param  array{0: int, 1: int}  $left
     * @param  array{0: int, 1: int}  $right
     */
    private function spansOverlap(array $left, array $right): bool
    {
        return $left[0] < $right[1] && $right[0] < $left[1];
    }

    /**
     * @param  array{0: int, 1: int}  $candidate
     * @param  list<array{0: int, 1: int}>  $spans
     */
    private function spanOverlapsAny(array $candidate, array $spans): bool
    {
        foreach ($spans as $span) {
            if ($this->spansOverlap($candidate, $span)) {
                return true;
            }
        }

        return false;
    }

    private function isAsciiDigit(string $character): bool
    {
        return strlen($character) === 1 && $character >= '0' && $character <= '9';
    }
}
