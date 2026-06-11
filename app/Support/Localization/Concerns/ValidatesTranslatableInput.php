<?php

namespace App\Support\Localization\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesTranslatableInput
{
    /**
     * Validate a field that accepts either a plain string (current locale) or
     * a locale-keyed array for Spatie translatable models.
     *
     * @param  list<string>  $supportedLocales
     */
    protected function validateTranslatableString(
        Validator $validator,
        string $field,
        array $supportedLocales,
        int $maxLength = 255,
    ): void {
        $value = $this->input($field);

        if ($value === null) {
            return;
        }

        if (is_string($value)) {
            if (mb_strlen($value) > $maxLength) {
                $validator->errors()->add($field, __('validation.max.string', [
                    'attribute' => $field,
                    'max' => $maxLength,
                ]));
            }

            return;
        }

        if (! is_array($value)) {
            $validator->errors()->add($field, __('validation.array', ['attribute' => $field]));

            return;
        }

        foreach ($value as $locale => $text) {
            if (! is_string($locale) || ! in_array($locale, $supportedLocales, true)) {
                $validator->errors()->add($field, __('validation.in', ['attribute' => "{$field}.{$locale}"]));

                continue;
            }

            if (! is_string($text)) {
                $validator->errors()->add($field, __('validation.string', ['attribute' => "{$field}.{$locale}"]));

                continue;
            }

            if (mb_strlen($text) > $maxLength) {
                $validator->errors()->add($field, __('validation.max.string', [
                    'attribute' => "{$field}.{$locale}",
                    'max' => $maxLength,
                ]));
            }
        }
    }

    /**
     * When the client sends a locale map, require a non-empty value for every
     * supported locale. Plain strings (API shorthand for the active locale) skip
     * this check.
     *
     * @param  list<string>  $supportedLocales
     */
    protected function validateTranslatableAllLocales(
        Validator $validator,
        string $field,
        array $supportedLocales,
    ): void {
        $value = $this->input($field);

        if (! is_array($value)) {
            return;
        }

        foreach ($supportedLocales as $locale) {
            $text = $value[$locale] ?? null;

            if (! is_string($text) || trim($text) === '') {
                $validator->errors()->add(
                    "{$field}.{$locale}",
                    __('validation.required', ['attribute' => "{$field}.{$locale}"]),
                );
            }
        }
    }
}
