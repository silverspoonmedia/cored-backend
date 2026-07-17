<?php

namespace App\Support\Localization;

use App\Domain\Identity\Models\User;

/**
 * Shared locale cookie + optional user-column persist for Web and API paths.
 */
final class LocalePreference
{
    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        $supported = config('localization.supported_locales', ['en', 'id', 'ja']);

        return is_array($supported) ? array_values(array_filter($supported, 'is_string')) : ['en', 'id', 'ja'];
    }

    public static function isSupported(string $locale): bool
    {
        return in_array($locale, self::supported(), true);
    }

    /**
     * Queue locale cookie and persist on the user when authenticated.
     */
    public static function apply(string $locale, ?User $user = null): void
    {
        LocaleCookie::queue($locale);

        if (! $user instanceof User) {
            return;
        }

        $column = config('localization.user_column');

        if (! is_string($column) || $column === '') {
            return;
        }

        if ($user->{$column} === $locale) {
            return;
        }

        $user->forceFill([$column => $locale])->save();
    }
}
