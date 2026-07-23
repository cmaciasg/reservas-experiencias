<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\CancelBookingService;
use App\Application\Exception\BookingNotFoundException;
use App\Domain\Booking;
use App\Domain\BookingCancellationPolicy;
use App\Domain\Exception\BookingAlreadyCancelledException;
use App\Domain\Exception\CancellationWindowExpiredException;
use App\Domain\Money;
use App\Domain\Session;
use App\Tests\Application\InMemory\InMemoryBookingRepository;
use App\Tests\Application\InMemory\InMemoryNotificationSender;
use App\Tests\Application\InMemory\InMemorySessionRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CancelBookingServiceTest extends TestCase
{
    private InMemorySessionRepository $sessions;
    private InMemoryBookingRepository $bookings;
    private InMemoryNotificationSender $notifications;
    private MockClock $clock;
    private CancelBookingService $service;

    protected function setUp(): void
    {
        $this->sessions = new InMemorySessionRepository();
        $this->bookings = new InMemoryBookingRepository();
        $this->notifications = new InMemoryNotificationSender();
        $this->clock = new MockClock('2026-08-01 10:00:00');
        $this->service = new CancelBookingService(
            $this->bookings,
            $this->sessions,
            new BookingCancellationPolicy(),
            $this->clock,
            $this->notifications,
        );

        $session = Session::schedule(
            'session-1',
            'exp-1',
            $this->clock->now()->modify('+3 days'),
            capacity: 10,
            price: Money::fromCents(1000),
            now: $this->clock->now(),
        );
        $session->decreaseAvailableSeats(4);
        $this->sessions->save($session);

        $this->bookings->save(Booking::confirm('booking-1', 'session-1', 'user-1', 4, Money::fromCents(4000)));
    }

    public function test_cancels_a_confirmed_booking_and_releases_its_seats(): void
    {
        $booking = $this->service->cancel('booking-1');

        self::assertTrue($booking->isCancelled());
        self::assertSame(10, $this->sessions->ofId('session-1')->availableSeats());
        self::assertSame(1, $this->notifications->cancelledNotificationsCount());
    }

    public function test_rejects_cancelling_a_non_existing_booking(): void
    {
        $this->expectException(BookingNotFoundException::class);

        $this->service->cancel('booking-unknown');
    }

    public function test_rejects_cancelling_an_already_cancelled_booking(): void
    {
        $this->service->cancel('booking-1');

        $this->expectException(BookingAlreadyCancelledException::class);

        $this->service->cancel('booking-1');
    }

    public function test_rejects_cancelling_less_than_24_hours_before_the_session_starts(): void
    {
        $this->clock->modify('+2 days 3 hours');

        $this->expectException(CancellationWindowExpiredException::class);

        $this->service->cancel('booking-1');
    }
}
