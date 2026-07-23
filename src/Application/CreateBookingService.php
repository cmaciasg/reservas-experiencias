<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Exception\SessionNotFoundException;
use App\Domain\Booking;
use App\Domain\Exception\NotEnoughSeatsAvailableException;
use App\Domain\Exception\SessionAlreadyStartedException;
use App\Domain\IdGenerator;
use App\Domain\Notification\NotificationSender;
use App\Domain\Repository\BookingRepository;
use App\Domain\Repository\SessionRepository;
use Psr\Clock\ClockInterface;

final class CreateBookingService
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly BookingRepository $bookings,
        private readonly IdGenerator $idGenerator,
        private readonly ClockInterface $clock,
        private readonly NotificationSender $notifications,
    ) {
    }

    public function create(string $sessionId, string $userId, int $seats): Booking
    {
        $session = $this->sessions->ofId($sessionId);
        if (null === $session) {
            throw new SessionNotFoundException(sprintf('Session "%s" not found.', $sessionId));
        }

        $now = $this->clock->now();

        if ($session->hasStartedAt($now)) {
            throw new SessionAlreadyStartedException(sprintf('Session "%s" has already started.', $sessionId));
        }

        // Atomic conditional UPDATE at the persistence layer (repeats the
        // "not started" check too, as a safety net against the session
        // starting between the check above and this call) — the only thing
        // that's actually safe under concurrent booking requests.
        if (!$this->sessions->reserveSeats($sessionId, $seats, $now)) {
            throw new NotEnoughSeatsAvailableException(sprintf('Not enough seats available for session "%s".', $sessionId));
        }

        $totalPrice = $session->price()->multiplyBy($seats);
        $booking = Booking::confirm($this->idGenerator->generate(), $sessionId, $userId, $seats, $totalPrice);

        $this->bookings->save($booking);
        $this->notifications->sendBookingConfirmed($booking);

        return $booking;
    }
}
