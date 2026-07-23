<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Exception\BookingNotFoundException;
use App\Application\Exception\SessionNotFoundException;
use App\Domain\Booking;
use App\Domain\BookingCancellationPolicy;
use App\Domain\Exception\BookingAlreadyCancelledException;
use App\Domain\Notification\NotificationSender;
use App\Domain\Repository\BookingRepository;
use App\Domain\Repository\SessionRepository;
use Psr\Clock\ClockInterface;

final class CancelBookingService
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly SessionRepository $sessions,
        private readonly BookingCancellationPolicy $cancellationPolicy,
        private readonly ClockInterface $clock,
        private readonly NotificationSender $notifications,
    ) {
    }

    public function cancel(string $bookingId): Booking
    {
        $booking = $this->bookings->ofId($bookingId);
        if (null === $booking) {
            throw new BookingNotFoundException(sprintf('Booking "%s" not found.', $bookingId));
        }

        if ($booking->isCancelled()) {
            throw new BookingAlreadyCancelledException(sprintf('Booking "%s" is already cancelled.', $bookingId));
        }

        $session = $this->sessions->ofId($booking->sessionId());
        if (null === $session) {
            throw new SessionNotFoundException(sprintf('Session "%s" not found.', $booking->sessionId()));
        }

        $this->cancellationPolicy->assertCancellable($session->date(), $this->clock->now());

        $booking->cancel();
        $this->bookings->save($booking);
        $this->sessions->releaseSeats($booking->sessionId(), $booking->seats());
        $this->notifications->sendBookingCancelled($booking);

        return $booking;
    }
}
