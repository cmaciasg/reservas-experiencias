<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Value object: an amount in cents (never floats), to avoid rounding errors
 * in prices and totals.
 */
final class Money
{
    private function __construct(private readonly int $amountInCents)
    {
        if ($this->amountInCents < 0) {
            throw new \InvalidArgumentException('Money amount cannot be negative.');
        }
    }

    public static function fromCents(int $amountInCents): self
    {
        return new self($amountInCents);
    }

    public function amountInCents(): int
    {
        return $this->amountInCents;
    }

    public function multiplyBy(int $times): self
    {
        if ($times < 0) {
            throw new \InvalidArgumentException('Cannot multiply money by a negative factor.');
        }

        return new self($this->amountInCents * $times);
    }

    public function add(self $other): self
    {
        return new self($this->amountInCents + $other->amountInCents);
    }

    public function equals(self $other): bool
    {
        return $this->amountInCents === $other->amountInCents;
    }
}
