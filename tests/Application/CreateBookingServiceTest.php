<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\CreateBookingService;
use App\Application\Exception\SessionNotFoundException;
use App\Domain\Exception\NotEnoughSeatsAvailableException;
use App\Domain\Exception\SessionAlreadyStartedException;
use App\Domain\Money;
use App\Domain\Session;
use App\Tests\Application\InMemory\InMemoryBookingRepository;
use App\Tests\Application\InMemory\InMemoryNotificationSender;
use App\Tests\Application\InMemory\InMemorySessionRepository;
use App\Tests\Application\InMemory\SequentialIdGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CreateBookingServiceTest extends TestCase
{
    private InMemorySessionRepository $sessions;
    private InMemoryBookingRepository $bookings;
    private InMemoryNotificationSender $notifications;
    private MockClock $clock;
    private CreateBookingService $service;

    protected function setUp(): void
    {
        $this->sessions = new InMemorySessionRepository();
        $this->bookings = new InMemoryBookingRepository();
        $this->notifications = new InMemoryNotificationSender();
        $this->clock = new MockClock('2026-08-01 10:00:00');
        $this->service = new CreateBookingService(
            $this->sessions,
            $this->bookings,
            new SequentialIdGenerator(),
            $this->clock,
            $this->notifications,
        );
    }

    public function test_books_seats_and_decreases_availability(): void
    {
        $this->sessions->save($this->scheduledSession(capacity: 10, pricePerSeatCents: 1500));

        $booking = $this->service->create('session-1', 'user-1', 3);

        self::assertSame(3, $booking->seats());
        self::assertSame(4500, $booking->totalPrice()->amountInCents());
        self::assertSame(7, $this->sessions->ofId('session-1')->availableSeats());
        self::assertSame($booking, $this->bookings->ofId($booking->id()));
        self::assertSame(1, $this->notifications->confirmedNotificationsCount());
    }

    public function test_rejects_booking_a_non_existing_session(): void
    {
        $this->expectException(SessionNotFoundException::class);

        $this->service->create('session-unknown', 'user-1', 1);
    }

    public function test_rejects_booking_more_seats_than_available(): void
    {
        $this->sessions->save($this->scheduledSession(capacity: 2, pricePerSeatCents: 1000));

        $this->expectException(NotEnoughSeatsAvailableException::class);

        $this->service->create('session-1', 'user-1', 3);
    }

    public function test_rejects_booking_a_session_that_has_already_started(): void
    {
        $session = Session::schedule(
            'session-1',
            'exp-1',
            $this->clock->now()->modify('+1 hour'),
            capacity: 10,
            price: Money::fromCents(1000),
            now: $this->clock->now(),
        );
        $this->sessions->save($session);

        $this->clock->modify('+2 hours');

        $this->expectException(SessionAlreadyStartedException::class);

        $this->service->create('session-1', 'user-1', 1);
    }

    private function scheduledSession(int $capacity, int $pricePerSeatCents): Session
    {
        return Session::schedule(
            'session-1',
            'exp-1',
            $this->clock->now()->modify('+1 day'),
            $capacity,
            Money::fromCents($pricePerSeatCents),
            $this->clock->now(),
        );
    }
}
