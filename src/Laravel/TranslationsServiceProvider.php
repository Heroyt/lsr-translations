<?php

declare(strict_types=1);

namespace Lsr\Translations\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use InvalidArgumentException;
use Lsr\Translations\GettextEnvironment;
use Lsr\Translations\GettextTranslator;
use Lsr\Translations\Localization;
use Lsr\Translations\MessageTranslator;
use Lsr\Translations\SupportedLanguages;

/**
 * Laravel adapter: Laravel owns the locale (`App::setLocale()`), this provider mirrors it into native gettext.
 *
 * Unsupported Laravel locales select the configured default language.
 */
final class TranslationsServiceProvider extends ServiceProvider
{
    private const string OCTANE_REQUEST_RECEIVED = 'Laravel\Octane\Events\RequestReceived';

    public function register(): void {
        $this->mergeConfigFrom(self::configFile(), 'lsr-translations');

        $this->app->singleton(
            SupportedLanguages::class,
            static fn (Application $app): SupportedLanguages => new SupportedLanguages(
                self::config($app)->array('lsr-translations.supported_languages'),
            ),
        );
        $this->app->singleton(GettextEnvironment::class, static function (Application $app): GettextEnvironment {
            $config = self::config($app);
            $directory = $config->string('lsr-translations.directory');
            $domains = [];
            foreach ($config->array('lsr-translations.domains') as $domain => $domainDirectory) {
                if ($domainDirectory !== null && ! is_string($domainDirectory)) {
                    throw new InvalidArgumentException('lsr-translations.domains values must be directories or null.');
                }
                $domains[(string) $domain] = $domainDirectory ?? $directory;
            }
            return new GettextEnvironment($config->string('lsr-translations.default_domain'), $directory, $domains);
        });
        $this->app->singleton(Localization::class, static function (Application $app): Localization {
            $localization = new Localization(
                $app->make(SupportedLanguages::class),
                $app->make(GettextEnvironment::class),
                self::config($app)->string('lsr-translations.default_language'),
            );
            self::select($localization, $app->getLocale());
            return $localization;
        });
        $this->app->singleton(GettextTranslator::class, static function (Application $app): GettextTranslator {
            // Resolving the localization applies the application locale before the first translation.
            $app->make(Localization::class);
            return new GettextTranslator(self::config($app)->string('lsr-translations.default_domain'));
        });
        $this->app->alias(GettextTranslator::class, MessageTranslator::class);
    }

    public function boot(Dispatcher $events): void {
        $this->publishes(
            [self::configFile() => $this->app->configPath('lsr-translations.php')],
            'lsr-translations-config',
        );

        $events->listen(LocaleUpdated::class, function (LocaleUpdated $event): void {
            $this->synchronize($event->locale);
        });
        // Octane reuses the worker process and resets Laravel's locale without LocaleUpdated.
        $events->listen(self::OCTANE_REQUEST_RECEIVED, function (object $event): void {
            $sandbox = property_exists($event, 'sandbox') ? $event->sandbox : null;
            $this->synchronize(($sandbox instanceof Application ? $sandbox : $this->app)->getLocale());
        });

        $this->callAfterResolving('blade.compiler', static function (BladeCompiler $blade): void {
            $blade->directive(
                'gettext',
                static fn (string $expression): string => '<?php echo e(app(\\' . MessageTranslator::class
                    . '::class)->translate(' . $expression . ')); ?>',
            );
        });
    }

    private function synchronize(string $locale): void {
        // An unresolved localization applies the current Laravel locale when it is first resolved.
        if ($this->app->resolved(Localization::class)) {
            self::select($this->app->make(Localization::class), $locale);
        }
    }

    private static function select(Localization $localization, string $locale): void {
        $localization->setLocale(
            $localization->supports($locale) ? $locale : $localization->defaultLanguage()->id,
        );
    }

    private static function config(Application $app): Repository {
        return $app->make(Repository::class);
    }

    private static function configFile(): string {
        return dirname(__DIR__, 2) . '/config/lsr-translations.php';
    }
}
