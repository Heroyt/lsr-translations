<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use Lsr\Translations\MessageFormatter;
use PHPUnit\Framework\TestCase;

final class MessageFormatterTest extends TestCase
{
    public function test_numeric_keys_use_sprintf_in_value_order(): void {
        self::assertSame(
            'Player Ada has 42 points',
            MessageFormatter::format('Player %s has %d points', [1 => 'Ada', 3 => 42]),
        );
    }

    public function test_string_keys_replace_trimmed_placeholders_and_keep_unknown_ones(): void {
        self::assertSame(
            'Player Ada has 42 points; %{missing} (100%)',
            MessageFormatter::format('Player %{ player } has %{points} points; %{missing} (100%)', ['player' => 'Ada', 'points' => 42]),
        );
    }

    public function test_mixed_keys_are_rejected(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Format parameters must use either only numeric keys or only string keys.');

        MessageFormatter::format('Player %s has %{points} points', ['Ada', 'points' => 42]);
    }

    public function test_non_scalar_values_are_rejected(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Format parameter values must be scalar or null.');

        MessageFormatter::format('Player %{player}', ['player' => []]);
    }
}
