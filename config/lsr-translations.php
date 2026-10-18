<?php

declare(strict_types=1);

return [
    /*
     * Language directory with `<locale>/LC_MESSAGES/<domain>.mo` files. Kept apart from Laravel's
     * PHP/JSON translation files.
     */
    'directory' => lang_path('gettext'),

    // Domain selected with textdomain() and used by translate() calls without an explicit domain.
    'default_domain' => 'messages',

    // Additional text domains: domain => language directory, or null to use `directory`.
    'domains' => [],

    // Language used when the Laravel locale is not supported.
    'default_language' => 'en_US',

    // Supported `language_COUNTRY` locales. Laravel locales such as `cs` resolve to `cs_CZ`.
    'supported_languages' => ['en_US'],
];
