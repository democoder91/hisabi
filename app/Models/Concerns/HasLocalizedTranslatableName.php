<?php

namespace App\Models\Concerns;

trait HasLocalizedTranslatableName
{
    public function getLocalizedName(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();

        $translations = $this->getSafeNameTranslations();

        return $translations[$locale]
            ?? $translations['en']
            ?? collect($translations)->first(fn (mixed $translation) => filled($translation));
    }

    public function getSafeNameTranslations(): array
    {
        $rawName = $this->getAttributes()['name'] ?? null;

        $translations = [];

        if (is_array($rawName)) {
            $translations = $rawName;
        }

        if (is_string($rawName)) {
            $decoded = json_decode($rawName, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $translations = $decoded;
            } else {
                $translations = ['en' => $rawName];
            }
        }

        return self::normalizeLocalizedNameTranslations($translations);
    }

    public static function normalizeLocalizedNameTranslations(array $translations): array
    {
        foreach ($translations as $locale => $translation) {
            $translations[$locale] = self::normalizeLocalizedNameValue($translation);
        }

        return $translations;
    }

    private static function normalizeLocalizedNameValue(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || preg_match('/\p{Arabic}/u', $value) === 1) {
            return $value;
        }

        if (preg_match('/[ØÙÃÂ]/u', $value) !== 1) {
            return $value;
        }

        foreach (['Windows-1252', 'ISO-8859-1'] as $encoding) {
            $normalized = @iconv($encoding, 'UTF-8//IGNORE', $value);

            if (is_string($normalized) && $normalized !== '' && preg_match('/\p{Arabic}/u', $normalized) === 1) {
                return $normalized;
            }
        }

        return $value;
    }
}