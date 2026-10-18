<?php

declare(strict_types=1);

namespace Tests\Support;

use FilesystemIterator;
use Gettext\Generator\MoGenerator;
use Gettext\Translation;
use Gettext\Translations;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/** Temporary language directory with compiled MO files; restores process-global locale state. */
final class GettextFixture
{
    public readonly string $directory;
    /** @var array<string, false|string> */
    private array $environment = [];
    private string|false $locale;

    public function __construct() {
        $this->directory = sys_get_temp_dir() . '/lsr-translations-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0777, true);
        foreach (['LANGUAGE', 'LANG', 'LC_ALL'] as $name) {
            $this->environment[$name] = getenv($name);
        }
        $this->locale = setlocale(LC_ALL, '0');
    }

    public static function supportsLocales(string ...$locales): bool {
        $previous = setlocale(LC_ALL, '0');
        try {
            foreach ($locales as $locale) {
                if (setlocale(LC_MESSAGES, $locale . '.UTF-8', $locale) === false) {
                    return false;
                }
            }
            return true;
        } finally {
            if ($previous !== false) {
                setlocale(LC_ALL, $previous);
            }
        }
    }

    /**
     * @param list<array{0: ?string, 1: string, 2: string|list<string>, 3?: string}> $entries
     *     [context, message, translation or plural translations, untranslated plural]
     */
    public function write(
        string $locale,
        string $domain,
        array $entries,
        int $pluralCount = 2,
        string $pluralExpression = 'n != 1',
    ): void {
        $translations = Translations::create($domain, $locale);
        $translations->getHeaders()->setPluralForm($pluralCount, $pluralExpression);
        foreach ($entries as $entry) {
            $translation = Translation::create($entry[0], $entry[1]);
            if (is_array($entry[2])) {
                $translation->setPlural($entry[3] ?? $entry[1]);
                $translation->translate($entry[2][0]);
                $translation->translatePlural(...array_slice($entry[2], 1));
            } else {
                $translation->translate($entry[2]);
            }
            $translations->add($translation);
        }
        $directory = $this->directory . '/' . $locale . '/LC_MESSAGES';
        if ( ! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents(
            $directory . '/' . $domain . '.mo',
            new MoGenerator()->includeHeaders()->generateString($translations),
        );
    }

    public function restore(): void {
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
        if ($this->locale !== false) {
            setlocale(LC_ALL, $this->locale);
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            assert($file instanceof SplFileInfo);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->directory);
    }
}
