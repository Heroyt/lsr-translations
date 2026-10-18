<?php

declare(strict_types=1);

namespace Lsr\Translations;

use Stringable;

/** Translates gettext messages for the currently active locale. */
interface MessageTranslator
{
    /**
     * Translate a message and interpolate it.
     *
     * @param string|null $plural Untranslated plural form; when set, `$num` selects the plural form.
     * @param string|null $context Gettext message context (`msgctxt`).
     * @param string|null $domain Text domain; `null` selects the translator's default domain.
     * @param array<int|string, bool|float|int|string|null> $format Integer keys use `sprintf`; string keys
     *     replace `%{name}` placeholders. Mixed key kinds are rejected.
     */
    public function translate(
        string | Stringable $message,
        ?string $plural = null,
        int $num = 1,
        ?string $context = null,
        ?string $domain = null,
        array $format = [],
    ): string;
}
