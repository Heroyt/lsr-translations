<?php

declare(strict_types=1);

namespace Lsr\Translations;

use InvalidArgumentException;

/**
 * Applies a locale to the process-global native gettext state.
 *
 * `setlocale()`, the environment and domain bindings are shared by the whole PHP process (and every
 * thread of a ZTS build). Apply locales sequentially; concurrent requests in one process need isolated
 * workers.
 */
final readonly class GettextEnvironment
{
    /** @var array<string, string> Additional text domain => language directory. */
    public array $domains;

    /**
     * @param string $defaultDomain Domain selected with `textdomain()` and used for messages without a domain.
     * @param string $directory Language directory containing `<locale>/LC_MESSAGES/<domain>.mo`.
     * @param array<array-key, mixed> $domains Additional domain => directory map.
     */
    public function __construct(
        public string $defaultDomain,
        public string $directory,
        array $domains = [],
    ) {
        self::assertDomain($defaultDomain);
        self::assertDirectory($directory);
        $normalized = [];
        foreach ($domains as $domain => $domainDirectory) {
            $domain = (string) $domain;
            self::assertDomain($domain);
            if ( ! is_string($domainDirectory)) {
                throw new InvalidArgumentException('The directory of text domain "' . $domain . '" must be a string.');
            }
            self::assertDirectory($domainDirectory);
            $normalized[$domain] = rtrim($domainDirectory, '/\\');
        }
        $this->domains = $normalized;
    }

    /** Return a copy that also binds `$domain`, by default to the main language directory. */
    public function withDomain(string $domain, ?string $directory = null): self {
        return new self($this->defaultDomain, $this->directory, [...$this->domains, $domain => $directory ?? $this->directory]);
    }

    /** @return array<string, string> Every bound domain => directory, starting with the default domain. */
    public function bindings(): array {
        $bindings = [$this->defaultDomain => rtrim($this->directory, '/\\')];
        foreach ($this->domains as $domain => $directory) {
            $bindings[$domain] = $directory;
        }
        return $bindings;
    }

    public function apply(Locale $locale): void {
        foreach (['LANGUAGE', 'LANG', 'LC_ALL'] as $variable) {
            putenv($variable . '=' . $locale->code);
        }
        $candidates = [
            $locale->code,
            $locale->code . '.UTF8',
            $locale->code . '.UTF-8',
            $locale->code . '.utf-8',
            $locale->language->name,
        ];
        setlocale(LC_ALL, $candidates);
        setlocale(LC_MESSAGES, $candidates);

        foreach ($this->bindings() as $domain => $directory) {
            bindtextdomain((string) $domain, $directory);
            bind_textdomain_codeset((string) $domain, 'UTF-8');
        }
        // Re-selecting the default domain also invalidates GNU gettext's translation cache.
        textdomain($this->defaultDomain);
    }

    private static function assertDomain(string $domain): void {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/D', $domain) !== 1) {
            throw new InvalidArgumentException('Text domain "' . $domain . '" must be a safe ASCII filename component.');
        }
    }

    private static function assertDirectory(string $directory): void {
        if (trim($directory) === '' || str_contains($directory, "\0")) {
            throw new InvalidArgumentException('The language directory must be a non-empty filesystem path.');
        }
    }
}
