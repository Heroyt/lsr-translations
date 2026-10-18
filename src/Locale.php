<?php

declare(strict_types=1);

namespace Lsr\Translations;

use Gettext\Languages\Language;

/** A supported language resolved to its gettext locale, for example `cs_CZ`. */
final readonly class Locale
{
    /**
     * @param non-empty-string $code Full gettext locale (`cs_CZ`).
     * @param non-empty-string $languageId Short language identifier (`cs`).
     * @param non-empty-string $country Country code (`CZ`).
     */
    public function __construct(
        public string $code,
        public string $languageId,
        public string $country,
        public Language $language,
    ) {
    }
}
