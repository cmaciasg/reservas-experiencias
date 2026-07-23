<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_multiplies_by_a_number_of_seats(): void
    {
        $pricePerSeat = Money::fromCents(1500);

        $total = $pricePerSeat->multiplyBy(3);

        self::assertSame(4500, $total->amountInCents());
    }

    public function test_adds_two_amounts(): void
    {
        $a = Money::fromCents(1000);
        $b = Money::fromCents(250);

        self::assertSame(1250, $a->add($b)->amountInCents());
    }

    public function test_two_amounts_with_the_same_cents_are_equal(): void
    {
        self::assertTrue(Money::fromCents(500)->equals(Money::fromCents(500)));
        self::assertFalse(Money::fromCents(500)->equals(Money::fromCents(501)));
    }

    public function test_rejects_a_negative_amount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::fromCents(-1);
    }
}
