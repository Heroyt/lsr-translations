<?php

declare(strict_types=1);

namespace Lsr\Translations;

use InvalidArgumentException;

/** Interpolates translated messages with `sprintf` (integer keys) or `%{name}` placeholders (string keys). */
final class MessageFormatter
{
    /**
     * @param array<array-key, mixed> $parameters
     * @phpstan-assert array<int|string, bool|float|int|string|null> $parameters
     */
    public static function assertParameters(array $parameters): void {
        $hasNumericKeys = false;
        $hasStringKeys = false;
        foreach ($parameters as $key => $value) {
            if ($value !== null && ! is_scalar($value)) {
                throw new InvalidArgumentException('Format parameter values must be scalar or null.');
            }
            $hasNumericKeys = $hasNumericKeys || is_int($key);
            $hasStringKeys = $hasStringKeys || is_string($key);
            if ($hasNumericKeys && $hasStringKeys) {
                throw new InvalidArgumentException(
                    'Format parameters must use either only numeric keys or only string keys.',
                );
            }
        }
    }

    /** @param array<array-key, mixed> $parameters */
    public static function format(string $message, array $parameters): string {
        if ($parameters === []) {
            return $message;
        }
        self::assertParameters($parameters);
        if (is_int(array_key_first($parameters))) {
            return sprintf($message, ...array_values($parameters));
        }

        return preg_replace_callback(
            '/%\{((?:.|\n)+?)\}/',
            static function (array $matches) use ($parameters): string {
                $key = trim($matches[1]);
                return array_key_exists($key, $parameters) ? (string) $parameters[$key] : $matches[0];
            },
            $message,
        ) ?? $message;
    }
}
