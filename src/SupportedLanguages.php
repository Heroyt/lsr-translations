<?php

declare(strict_types=1);

namespace Lsr\Translations;

use Gettext\Languages\Language;
use InvalidArgumentException;

/** Immutable set of supported languages, each mapped to exactly one country. */
final readonly class SupportedLanguages
{
    /** @var array<non-empty-string, non-empty-string> Language identifier => country (`cs` => `CZ`). */
    public array $languages;

    /**
     * @param array<array-key, mixed> $languages A list of `ll_CC` codes, or a map of `ll => CC` / `ll => ll_CC`.
     */
    public function __construct(array $languages) {
        $normalized = [];
        foreach ($languages as $key => $value) {
            if ( ! is_string($value)) {
                throw new InvalidArgumentException('Supported languages must be strings.');
            }
            $split = explode('_', $value);
            if (count($split) === 2) {
                [$language, $country] = $split;
            } elseif (is_string($key)) {
                $language = $key;
                $country = $value;
            } else {
                throw new InvalidArgumentException(
                    'Supported language "' . $value . '" must use the language_COUNTRY form.',
                );
            }
            if ($language === '' || $country === '') {
                throw new InvalidArgumentException(
                    'Supported language "' . $value . '" must use the language_COUNTRY form.',
                );
            }
            $normalized[$language] = $country;
        }
        $this->languages = $normalized;
    }

    public function find(string $language): ?Language {
        return Language::getById($language);
    }

    public function supports(string $language): bool {
        $found = $this->find($language);
        return $found !== null && isset($this->languages[self::languageId($found)]);
    }

    /**
     * Resolve a language code (`cs`, `cs_CZ`) to the supported locale (`cs_CZ`).
     *
     * @throws InvalidLanguageException
     */
    public function resolve(string $language): Locale {
        $found = $this->find($language);
        if ($found === null) {
            throw new InvalidLanguageException('Invalid language "' . $language . '"');
        }
        $id = self::languageId($found);
        if ($id === '' || ! isset($this->languages[$id])) {
            throw new InvalidLanguageException(
                'Unsupported language ' . $language . ' (' . $id . '). Supported languages are: '
                . implode(',', array_keys($this->languages)),
            );
        }
        $country = $this->languages[$id];

        return new Locale($id . '_' . $country, $id, $country, $found);
    }

    /** Short language identifier: `cs_CZ` => `cs`; identifiers with a script or region variant stay whole. */
    public static function languageId(Language $language): string {
        $split = explode('_', $language->id);
        return count($split) === 2 ? $split[0] : $language->id;
    }
}
