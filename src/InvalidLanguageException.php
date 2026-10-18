<?php

declare(strict_types=1);

namespace Lsr\Translations;

use InvalidArgumentException;

/** A requested language is unknown or not among the supported languages. */
class InvalidLanguageException extends InvalidArgumentException
{
}
