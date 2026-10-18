<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use Lsr\Translations\InvalidLanguageException;
use Lsr\Translations\SupportedLanguages;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SupportedLanguagesTest extends TestCase
{
    /** @return iterable<string, array{array<array-key, string>}> */
    public static function equivalentForms(): iterable {
        yield 'locale list' => [['cs_CZ', 'en_US']];
        yield 'country map' => [['cs' => 'CZ', 'en' => 'US']];
        yield 'locale map' => [['cs' => 'cs_CZ', 'en' => 'en_US']];
    }

    /** @param array<array-key, string> $languages */
    #[DataProvider('equivalentForms')]
    public function test_configuration_forms_normalize_to_language_country_map(array $languages): void {
        self::assertSame(['cs' => 'CZ', 'en' => 'US'], new SupportedLanguages($languages)->languages);
    }

    public function test_short_and_full_codes_resolve_to_the_supported_locale(): void {
        $languages = new SupportedLanguages(['cs_CZ', 'en_GB']);

        $czech = $languages->resolve('cs');
        self::assertSame(['cs_CZ', 'cs', 'CZ'], [$czech->code, $czech->languageId, $czech->country]);
        self::assertSame('en_GB', $languages->resolve('en_US')->code);
        self::assertTrue($languages->supports('en'));
        self::assertFalse($languages->supports('de'));
        self::assertFalse($languages->supports('not-a-language'));
    }

    public function test_unknown_and_unsupported_languages_are_distinguished(): void {
        $languages = new SupportedLanguages(['cs_CZ']);

        try {
            $languages->resolve('xx-invalid');
            self::fail('Unknown language was resolved.');
        } catch (InvalidLanguageException $exception) {
            self::assertSame('Invalid language "xx-invalid"', $exception->getMessage());
        }
        $this->expectException(InvalidLanguageException::class);
        $this->expectExceptionMessage('Unsupported language de (de). Supported languages are: cs');
        $languages->resolve('de');
    }

    public function test_list_entries_without_a_country_are_rejected(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Supported language "cs" must use the language_COUNTRY form.');

        new SupportedLanguages(['cs']);
    }
}
