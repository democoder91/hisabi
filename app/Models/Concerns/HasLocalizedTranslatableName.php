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

        if (is_array($rawName)) {
            return $rawName;
        }

        if (is_string($rawName)) {
            $decoded = json_decode($rawName, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return ['en' => $rawName];
        }

        return [];
    }
}