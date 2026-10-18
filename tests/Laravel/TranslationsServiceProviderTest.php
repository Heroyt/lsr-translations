<?php

declare(strict_types=1);

namespace Tests\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Lsr\Translations\Laravel\TranslationsServiceProvider;
use Lsr\Translations\Localization;
use Lsr\Translations\MessageTranslator;
use Orchestra\Testbench\TestCase;
use Tests\Support\GettextFixture;

final class TranslationsServiceProviderTest extends TestCase
{
    private GettextFixture $fixture;
    private string $domain;

    protected function setUp(): void {
        if ( ! GettextFixture::supportsLocales('cs_CZ', 'de_DE', 'en_US')) {
            self::markTestSkipped('Laravel gettext tests require cs_CZ, de_DE and en_US locales.');
        }
        $this->fixture = new GettextFixture();
        $this->domain = 'laravel_' . bin2hex(random_bytes(4));
        $this->fixture->write('cs_CZ', $this->domain, [[null, 'Hello %{name}', 'Ahoj %{name}']]);
        $this->fixture->write('de_DE', $this->domain, [[null, 'Hello %{name}', 'Hallo %{name}']]);
        parent::setUp();
    }

    protected function tearDown(): void {
        parent::tearDown();
        if (isset($this->fixture)) {
            $this->fixture->restore();
        }
    }

    protected function getPackageProviders($app): array {
        return [TranslationsServiceProvider::class];
    }

    protected function defineEnvironment($app): void {
        $config = $app->make(Repository::class);
        $config->set('app.locale', 'cs');
        $config->set('lsr-translations.directory', $this->fixture->directory);
        $config->set('lsr-translations.default_domain', $this->domain);
        $config->set('lsr-translations.default_language', 'en_US');
        $config->set('lsr-translations.supported_languages', ['cs_CZ', 'de_DE', 'en_US']);
    }

    public function test_first_translation_uses_the_laravel_locale_and_follows_locale_changes(): void {
        $app = $this->application();
        $translator = $app->make(MessageTranslator::class);

        self::assertSame('Ahoj Ada', $translator->translate('Hello %{name}', format: ['name' => 'Ada']));
        $app->setLocale('de');
        self::assertSame('Hallo Ada', $translator->translate('Hello %{name}', format: ['name' => 'Ada']));
        self::assertSame('de_DE', $app->make(Localization::class)->locale()->code);
    }

    public function test_unsupported_laravel_locale_selects_the_default_language(): void {
        $app = $this->application();
        $translator = $app->make(MessageTranslator::class);
        $app->setLocale('fr');

        self::assertSame('en_US', $app->make(Localization::class)->locale()->code);
        self::assertSame('Hello Ada', $translator->translate('Hello %{name}', format: ['name' => 'Ada']));
    }

    public function test_octane_request_reapplies_the_sandbox_locale(): void {
        $app = $this->application();
        $localization = $app->make(Localization::class);
        $localization->setLocale('de');

        // Octane resets Laravel's locale for the next request without dispatching LocaleUpdated.
        $app->make(Dispatcher::class)->dispatch('Laravel\Octane\Events\RequestReceived', [(object) ['sandbox' => $app]]);

        self::assertSame('cs_CZ', $localization->locale()->code);
    }

    public function test_blade_directive_escapes_translated_output(): void {
        $this->fixture->write('cs_CZ', $this->domain . '_html', [[null, 'Hello %{name}', 'Ahoj <b>%{name}</b>']]);
        $this->application()->make(Repository::class)->set('lsr-translations.default_domain', $this->domain . '_html');

        self::assertSame(
            'Ahoj &lt;b&gt;&lt;i&gt;Ada&lt;/i&gt;&lt;/b&gt;',
            Blade::render("@gettext('Hello %{name}', format: ['name' => \$name])", ['name' => '<i>Ada</i>']),
        );
    }

    private function application(): Application {
        self::assertNotNull($this->app);
        return $this->app;
    }
}
