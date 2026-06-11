<?php

namespace App\Support\Localization;

final class TranslationResolver
{
    /**
     * Resolve a Spatie-style translations map for the active (or given) locale.
     *
     * @param  array<string, string>  $translations
     */
    public static function resolve(array $translations, ?string $locale = null): string
    {
        if ($translations === []) {
            return '';
        }

        $locale ??= app()->getLocale();
        $fallback = (string) config('app.fallback_locale', 'en');

        if (isset($translations[$locale]) && $translations[$locale] !== '') {
            return $translations[$locale];
        }

        if (isset($translations[$fallback]) && $translations[$fallback] !== '') {
            return $translations[$fallback];
        }

        foreach ($translations as $value) {
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
