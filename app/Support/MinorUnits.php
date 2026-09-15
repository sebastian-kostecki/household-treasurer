<?php

namespace App\Support;

final class MinorUnits
{
    public static function fromMajorInput(string $value): ?int
    {
        $normalized = str_replace([' ', "\u{00A0}"], '', trim($value));
        $normalized = str_replace(',', '.', $normalized);

        if (! preg_match('/^-?\d+(\.\d{0,2})?$/', $normalized)) {
            return null;
        }

        $negative = str_starts_with($normalized, '-');
        $absolute = ltrim($normalized, '-');
        [$zlPart, $grPart] = array_pad(explode('.', $absolute, 2), 2, '0');
        $minorUnits = ((int) $zlPart * 100) + (int) str_pad(substr($grPart, 0, 2), 2, '0');

        return $negative ? -$minorUnits : $minorUnits;
    }

    public static function toMajorDisplay(int $minorUnits): string
    {
        $negative = $minorUnits < 0;
        $absolute = abs($minorUnits);
        $zl = intdiv($absolute, 100);
        $gr = $absolute % 100;
        $formatted = $zl.','.str_pad((string) $gr, 2, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').$formatted;
    }
}
