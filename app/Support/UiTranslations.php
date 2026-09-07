<?php

namespace App\Support;

class UiTranslations
{
    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $locale = (string) config('app.locale');
        $translations = trans('ui', locale: $locale);

        if (! is_array($translations) && $locale !== 'pl') {
            $translations = trans('ui', locale: 'pl');
        }

        return is_array($translations) ? $translations : [];
    }
}
