<?php

declare(strict_types=1);

namespace Lsr\Translations;

use InvalidArgumentException;
use Stringable;

/**
 * Native gettext translator. It reads the process-global locale applied by {@see GettextEnvironment}.
 */
final readonly class GettextTranslator implements MessageTranslator
{
    public function __construct(private string $defaultDomain) {
        if ($defaultDomain === '') {
            throw new InvalidArgumentException('The default text domain must not be empty.');
        }
    }

    public function translate(
        string | Stringable $message,
        ?string $plural = null,
        int $num = 1,
        ?string $context = null,
        ?string $domain = null,
        array $format = [],
    ): string {
        $message = (string) $message;
        if ($message === '') {
            return '';
        }
        $domain ??= $this->defaultDomain;
        $id = $context === null || $context === '' ? $message : $context . "\004" . $message;

        $translated = $plural === null || $plural === ''
            ? dgettext($domain, $id)
            : dngettext($domain, $id, $plural, $num);

        // An untranslated contextual message is returned with its context prefix.
        $separator = strpos($translated, "\004");
        if ($separator !== false) {
            $translated = substr($translated, $separator + 1);
        }

        return MessageFormatter::format($translated, $format);
    }
}
