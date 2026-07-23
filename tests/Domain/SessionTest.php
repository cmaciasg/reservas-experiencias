<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Exception\NotEnoughSeatsAvailableException;
use App\Domain\Exception\PastSessionDateException;
use App\Domain\Money;
use App\Domain\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-08-01 10:00:00');
    }

    public function test_schedules_a_session_with_full_availability(): void
    {
        $session = Session::schedule(
            'session-1',
            'exp-1',
            $this->now->modify('+1 day'),
            capacity: 20,
            price: Money::fromCents(2000),
            now: $this->now,
        );

        self::assertSame(20, $session->capacity());
        self::assertSame(20, $session->availableSeats());
    }

    public function test_rejects_a_session_scheduled_in_the_past(): void
    {
        $this->expectException(PastSessionDateException::class);

        Session::schedule(
            'session-1',
            'exp-1',
            $this->now->modify('-1 day'),
            capacity: 20,
            price: Money::fromCents(2000),
            now: $this->now,
        );
    }

    public function test_rejects_a_non_positive_capacity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Session::schedule(
            'session-1',
            'exp-1',
            $this->now->modify('+1 day'),
            capacity: 0,
            price: Money::fromCents(2000),
            now: $this->now,
        );
    }

    public function test_decreasing_seats_reduces_availability(): void
    {
        $session = $this->scheduledSession(capacity: 10);

        $session->decreaseAvailableSeats(3);

        self::assertSame(7, $session->availableSeats());
    }

    public function test_cannot_decrease_more_seats_than_available(): void
    {
        $session = $this->scheduledSession(capacity: 2);

        $this->expectException(NotEnoughSeatsAvailableException::class);

        $session->decreaseAvailableSeats(3);
    }

    public function test_increasing_seats_restores_availability_without_exceeding_capacity(): void
    {
        $session = $this->scheduledSession(capacity: 5);
        $session->decreaseAvailableSeats(4);

        $session->increaseAvailableSeats(1);
        self::assertSame(2, $session->availableSeats());

        $session->increaseAvailableSeats(10);
        self::assertSame(5, $session->availableSeats());
    }

    public function test_has_started_once_now_reaches_the_session_date(): void
    {
        $session = $this->scheduledSession(capacity: 5);

        self::assertFalse($session->hasStartedAt($this->now));
        self::assertTrue($session->hasStartedAt($this->now->modify('+2 days')));
    }

    private function scheduledSession(int $capacity): Session
    {
        return Session::schedule(
            'session-1',
            'exp-1',
            $this->now->modify('+1 day'),
            capacity: $capacity,
            price: Money::fromCents(2000),
            now: $this->now,
        );
    }
}
