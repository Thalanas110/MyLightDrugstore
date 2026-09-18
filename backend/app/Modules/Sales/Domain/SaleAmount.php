<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain;

use InvalidArgumentException;
use OverflowException;

final readonly class SaleAmount
{
    private function __construct(private int $cents) {}

    public static function fromDecimal(string $amount): self
    {
        if (preg_match('/\A(\d{1,15})\.(\d{2})\z/D', $amount, $matches) !== 1) {
            throw new InvalidArgumentException('Sale amounts must be decimal strings with two fractional digits.');
        }

        return new self(((int) $matches[1] * 100) + (int) $matches[2]);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public function multiply(int $multiplier): self
    {
        if ($multiplier < 1) {
            throw new InvalidArgumentException('Sale amount multipliers must be positive integers.');
        }

        if ($this->cents > intdiv(PHP_INT_MAX, $multiplier)) {
            throw new OverflowException('The sale amount exceeds the supported integer range.');
        }

        return new self($this->cents * $multiplier);
    }

    public function add(self $amount): self
    {
        if ($this->cents > PHP_INT_MAX - $amount->cents) {
            throw new OverflowException('The sale amount exceeds the supported integer range.');
        }

        return new self($this->cents + $amount->cents);
    }

    public function toDecimal(): string
    {
        return intdiv($this->cents, 100).'.'.str_pad((string) ($this->cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
