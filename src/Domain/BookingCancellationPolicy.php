<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exception\CancellationWindowExpiredException;

/**
 * Pure domain service: a booking cannot be cancelled less than 24h before
 * the session it belongs to starts. Doesn't belong to Session nor Booking
 * alone, since it relates both.
 */
final class BookingCancellationPolicy
{
    private const CANCELLATION_WINDOW_HOURS = 24;

    public function canCancel(\DateTimeImmutable $sessionStart, \DateTimeImmutable $now): bool
    {
        $deadline = $sessionStart->modify(sprintf('-%d hours', self::CANCELLATION_WINDOW_HOURS));

        return $now <= $deadline;
    }

    public function assertCancellable(\DateTimeImmutable $sessionStart, \DateTimeImmutable $now): void
    {
        if (!$this->canCancel($sessionStart, $now)) {
            throw new CancellationWindowExpiredException(
                sprintf('Bookings cannot be cancelled less than %d hours before the session starts.', self::CANCELLATION_WINDOW_HOURS),
            );
        }
    }
}
