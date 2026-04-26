<?php

namespace App\Domains\Search\Support;

/**
 * Description of one searchable document slice indexed for a source model.
 *
 * `field` identifies the underlying column (e.g. `name`, `note`, `description`).
 * `locale` is the translation key for translatable fields, or null for plain text.
 * `content` is the trimmed text that gets embedded and matched against.
 */
class SearchableDocument
{
    public function __construct(
        public readonly string $field,
        public readonly ?string $locale,
        public readonly string $content,
    ) {
    }
}
