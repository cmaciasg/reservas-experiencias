<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\BookingCancellationPolicy;
use App\Domain\Exception\CancellationWindowExpiredException;
use PHPUnit\Framework\TestCase;

final class BookingCancellationPolicyTest extends TestCase
{
    private BookingCancellationPolicy $policy;
    private \DateTimeImmutable $sessionStart;

    protected function setUp(): void
    {
        $this->policy = new BookingCancellationPolicy();
        $this->sessionStart = new \DateTimeImmutable('2026-08-10 18:00:00');
    }

    public function test_can_cancel_more_than_24_hours_before_the_session(): void
    {
        $now = $this->sessionStart->modify('-25 hours');

        self::assertTrue($this->policy->canCancel($this->sessionStart, $now));
    }

    public function test_can_cancel_exactly_at_the_24_hour_boundary(): void
    {
        $now = $this->sessionStart->modify('-24 hours');

        self::assertTrue($this->policy->canCancel($this->sessionStart, $now));
    }

    public function test_cannot_cancel_less_than_24_hours_before_the_session(): void
    {
        $now = $this->sessionStart->modify('-23 hours');

        self::assertFalse($this->policy->canCancel($this->sessionStart, $now));
    }

    public function test_assert_cancellable_throws_once_the_window_has_expired(): void
    {
        $this->expectException(CancellationWindowExpiredException::class);

        $this->policy->assertCancellable($this->sessionStart, $this->sessionStart->modify('-1 hour'));
    }
}
