<?php

declare(strict_types=1);

namespace Tests\Native;

use Lsr\Translations\GettextEnvironment;
use Lsr\Translations\GettextTranslator;
use Lsr\Translations\InvalidLanguageException;
use Lsr\Translations\Localization;
use Lsr\Translations\SupportedLanguages;
use PHPUnit\Framework\TestCase;
use Tests\Support\GettextFixture;

final class NativeGettextTest extends TestCase
{
    private const string CZECH_PLURALS = '(n==1) ? 0 : ((n>=2 && n<=4) ? 1 : 2)';

    private GettextFixture $fixture;
    private string $domain;
    private string $otherDomain;

    protected function setUp(): void {
        if ( ! GettextFixture::supportsLocales('cs_CZ', 'de_DE')) {
            self::markTestSkipped('Native gettext tests require cs_CZ and de_DE locales.');
        }
        $this->fixture = new GettextFixture();
        // Unique domains avoid native gettext's per-process catalog cache between tests.
        $suffix = bin2hex(random_bytes(4));
        $this->domain = 'messages_' . $suffix;
        $this->otherDomain = 'other_' . $suffix;

        $this->fixture->write('cs_CZ', $this->domain, [
            [null, 'Hello %{name}', 'Ahoj %{name}'],
            ['menu', 'Open', 'Otevřít menu'],
            [null, 'Open', 'Otevřít'],
            [null, '%d player', ['%d hráč', '%d hráči', '%d hráčů'], '%d players'],
        ], 3, self::CZECH_PLURALS);
        $this->fixture->write('de_DE', $this->domain, [
            [null, 'Hello %{name}', 'Hallo %{name}'],
            [null, '%d player', ['%d Spieler', '%d Spieler'], '%d players'],
        ]);
        $this->fixture->write('cs_CZ', $this->otherDomain, [[null, 'Hello %{name}', 'Nazdar %{name}']]);
    }

    protected function tearDown(): void {
        if (isset($this->fixture)) {
            $this->fixture->restore();
        }
    }

    public function test_sequential_locale_switches_translate_with_the_active_locale(): void {
        $localization = $this->localization('cs_CZ');
        $translator = new GettextTranslator($this->domain);

        self::assertSame('Ahoj Ada', $translator->translate('Hello %{name}', format: ['name' => 'Ada']));
        self::assertSame('de_DE', $localization->setLocale('de')->code);
        self::assertSame('Hallo Ada', $translator->translate('Hello %{name}', format: ['name' => 'Ada']));
        self::assertSame('cs_CZ', $localization->setLocale('cs_CZ')->code);
        self::assertSame('Ahoj Ada', $translator->translate('Hello %{name}', format: ['name' => 'Ada']));
    }

    public function test_context_plural_and_domain_selection(): void {
        $this->localization('cs_CZ');
        $translator = new GettextTranslator($this->domain);

        self::assertSame('Otevřít menu', $translator->translate('Open', context: 'menu'));
        self::assertSame('Otevřít', $translator->translate('Open'));
        self::assertSame('Close', $translator->translate('Close', context: 'menu'));
        self::assertSame('1 hráč', $translator->translate('%d player', '%d players', 1, format: [1]));
        self::assertSame('3 hráči', $translator->translate('%d player', '%d players', 3, format: [3]));
        self::assertSame('5 hráčů', $translator->translate('%d player', '%d players', 5, format: [5]));
        self::assertSame('5 lights', $translator->translate('%d light', '%d lights', 5, format: [5]));
        self::assertSame('Nazdar Ada', $translator->translate('Hello %{name}', domain: $this->otherDomain, format: ['name' => 'Ada']));
    }

    public function test_unsupported_locale_is_rejected_and_keeps_the_active_locale(): void {
        $localization = $this->localization('cs_CZ');

        try {
            $localization->setLocale('fr');
            self::fail('Unsupported locale was accepted.');
        } catch (InvalidLanguageException $exception) {
            self::assertStringStartsWith('Unsupported language fr (fr).', $exception->getMessage());
        }
        self::assertSame('cs_CZ', $localization->locale()->code);
        self::assertSame('Ahoj Ada', new GettextTranslator($this->domain)->translate('Hello %{name}', format: ['name' => 'Ada']));
    }

    private function localization(string $default): Localization {
        return new Localization(
            new SupportedLanguages(['cs_CZ', 'de_DE']),
            new GettextEnvironment($this->domain, $this->fixture->directory, [$this->otherDomain => $this->fixture->directory]),
            $default,
        );
    }
}
