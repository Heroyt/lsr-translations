# LSR Translations

`lsr/translations` is a framework-neutral native gettext runtime: supported-language resolution, process locale/domain binding, and contextual/plural message translation with `sprintf` or `%{name}` interpolation. Namespace: `Lsr\Translations\`.

It is the translation engine of [`lsr/core`](https://github.com/Heroyt/lsr-core) (0.7+) and of the optional `TextTranslator` of [`lsr/text-catalog`](https://github.com/Heroyt/lsr-text-catalog), and it ships an optional Laravel 12+ service provider.

## Installation

```sh
composer require lsr/translations
```

The package is available from Packagist and from the LSR Satis repository (`https://packages.laserliga.cz`).

## Requirements

- PHP `>=8.4`, `ext-gettext` and `gettext/languages ^2.10`.
- Installed OS locales for every supported language (`locale -a`); a missing OS locale silently leaves messages untranslated.
- Optional: `laravel/framework ^12.0 || ^13.0` for the service provider. Older Laravel versions conflict.

## Usage

```php
use Lsr\Translations\GettextEnvironment;
use Lsr\Translations\GettextTranslator;
use Lsr\Translations\Localization;
use Lsr\Translations\SupportedLanguages;

$localization = new Localization(
    new SupportedLanguages(['cs_CZ', 'en_US']),
    new GettextEnvironment(
        defaultDomain: 'messages',
        directory: __DIR__ . '/languages',          // <locale>/LC_MESSAGES/<domain>.mo
        domains: ['results' => __DIR__ . '/languages'],
    ),
    defaultLanguage: 'cs_CZ',                         // applied immediately
);
$translator = new GettextTranslator('messages');

$localization->setLocale('en');                     // resolves to en_US and applies it
echo $translator->translate('Hello %{name}', format: ['name' => 'Ada']);
echo $translator->translate('%d player', '%d players', 3, format: [3]);
echo $translator->translate('Open', context: 'menu', domain: 'results');
```

- `SupportedLanguages` accepts a list of `language_COUNTRY` codes or a `language => COUNTRY` map; each language maps to one country. `resolve('cs')` and `resolve('cs_CZ')` both return the `cs_CZ` `Locale`. Unknown or unsupported languages throw `InvalidLanguageException`.
- `GettextEnvironment::apply()` sets `LANGUAGE`, `LANG`, `LC_ALL`, `setlocale(LC_ALL/LC_MESSAGES)`, binds every domain with UTF-8 and re-selects the default domain, which also invalidates GNU gettext's translation cache.
- `Localization::setLocale()` applies only when the resolved locale changes; a rejected locale keeps the active one.
- `MessageTranslator::translate()` uses the gettext context (`msgctxt`), selects the plural form with `dngettext` whenever a plural is given, and strips the context from untranslated messages. Integer `format` keys use `sprintf`; string keys replace `%{name}` placeholders (vue-gettext compatible). Mixed key kinds and non-scalar values are rejected.
- Output is unescaped data: render it through the consumer's normal escaping.

### Process-global state

Native gettext state belongs to the PHP process and, in ZTS builds, to every thread. Apply locales sequentially. Long-running workers (RoadRunner, Octane, queue workers) must apply the locale of each request or job; concurrent requests in one process, such as threaded FrankenPHP workers, are not supported by native gettext.

## Laravel

The service provider is auto-discovered. Publish the configuration when the defaults do not fit:

```sh
php artisan vendor:publish --tag=lsr-translations-config
```

```php
// config/lsr-translations.php
return [
    'directory' => lang_path('gettext'),      // kept apart from Laravel's PHP/JSON translations
    'default_domain' => 'messages',
    'domains' => ['results' => null],         // null uses `directory`
    'default_language' => 'en_US',            // used for unsupported Laravel locales
    'supported_languages' => ['en_US', 'cs_CZ'],
];
```

Laravel owns locale selection. The provider mirrors `App::getLocale()` into gettext when the localization is first resolved, on every `LocaleUpdated` event, and on Octane's `RequestReceived`. Laravel locales such as `cs` resolve to `cs_CZ`; unsupported locales select `default_language`.

Inject `Lsr\Translations\MessageTranslator` (bound to `GettextTranslator`) or use the Blade directive, which escapes its output:

```blade
@gettext('Hello %{name}', format: ['name' => $user->name])
@gettext('%d player', '%d players', $count, format: [$count])
```

Laravel's own `__()`, `trans()` and `@lang` keep working with Laravel's PHP/JSON translations.

## Development and verification

```sh
composer install
composer validate --strict --no-check-publish
composer phpstan
composer test
composer cs
```

Native tests need the `cs_CZ`, `de_DE` and `en_US` OS locales. CI runs PHP 8.4 and 8.5 against Laravel 12 and 13 (Testbench 10 and 11).

## License

[MIT](LICENSE), copyright (c) 2026 Tomáš Vojík.
