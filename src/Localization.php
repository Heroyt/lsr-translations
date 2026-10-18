<?php

declare(strict_types=1);

namespace Lsr\Translations;

use Gettext\Languages\Language;

/**
 * The application's current and default language, applied to native gettext.
 *
 * Construction applies the default language. Changing the locale applies it to the process immediately;
 * selecting the already active locale is a no-op.
 */
final class Localization
{
    private Language $defaultLanguage;
    private Locale $locale;

    /** @throws InvalidLanguageException */
    public function __construct(
        public readonly SupportedLanguages $languages,
        public readonly GettextEnvironment $environment,
        string $defaultLanguage,
    ) {
        $this->setDefaultLanguage($defaultLanguage);
        $this->locale = $this->languages->resolve($defaultLanguage);
        $this->environment->apply($this->locale);
    }

    public function locale(): Locale {
        return $this->locale;
    }

    /** @throws InvalidLanguageException */
    public function setLocale(string $language): Locale {
        $locale = $this->languages->resolve($language);
        if ($locale->code !== $this->locale->code) {
            $this->locale = $locale;
            $this->environment->apply($locale);
        }
        return $this->locale;
    }

    public function supports(string $language): bool {
        return $this->languages->supports($language);
    }

    public function defaultLanguage(): Language {
        return $this->defaultLanguage;
    }

    /** Short identifier of the default language (`cs`). */
    public function defaultLanguageId(): string {
        return SupportedLanguages::languageId($this->defaultLanguage);
    }

    /**
     * Change the default language. It must be a known language; support is checked when it is selected.
     *
     * @throws InvalidLanguageException
     */
    public function setDefaultLanguage(string $language): void {
        $found = $this->languages->find($language);
        if ($found === null) {
            throw new InvalidLanguageException('Invalid language "' . $language . '"');
        }
        $this->defaultLanguage = $found;
    }
}
