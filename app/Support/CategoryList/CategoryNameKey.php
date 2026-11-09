<?php

namespace App\Support\CategoryList;

/**
 * The name key of a Category (ADR-0063, "Names"): the name trimmed of Unicode
 * white space and lower-cased by Unicode rules. Diacritics count.
 *
 * The one place that computes the key: the data migration, the conversion of
 * snapshots 1 to 5 and the validation of the list's uniqueness all call it.
 * The key is stored in `categories.name_key`, never computed by the database,
 * because PostgreSQL `lower()` depends on the collation (ADR-0065).
 */
final class CategoryNameKey
{
    public static function of(string $name): string
    {
        return mb_strtolower(self::trim($name));
    }

    /**
     * Trims white space of every Unicode kind from both ends of the name.
     */
    public static function trim(string $name): string
    {
        return (string) preg_replace('/^[\p{Z}\s]+|[\p{Z}\s]+$/u', '', $name);
    }
}
