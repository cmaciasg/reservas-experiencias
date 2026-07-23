<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Booking;
use App\Domain\BookingStatus;
use App\Domain\Exception\BookingAlreadyCancelledException;
use App\Domain\Money;
use PHPUnit\Framework\TestCase;

final class BookingTest extends TestCase
{
    public function test_confirms_a_booking_for_a_number_of_seats(): void
    {
        $booking = Booking::confirm('booking-1', 'session-1', 'user-1', 2, Money::fromCents(4000));

        self::assertSame(BookingStatus::Confirmed, $booking->status());
        self::assertSame(2, $booking->seats());
        self::assertFalse($booking->isCancelled());
    }

    public function test_rejects_a_booking_with_zero_seats(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Booking::confirm('booking-1', 'session-1', 'user-1', 0, Money::fromCents(4000));
    }

    public function test_cancelling_a_confirmed_booking_marks_it_as_cancelled(): void
    {
        $booking = Booking::confirm('booking-1', 'session-1', 'user-1', 2, Money::fromCents(4000));

        $booking->cancel();

        self::assertTrue($booking->isCancelled());
        self::assertSame(BookingStatus::Cancelled, $booking->status());
    }

    public function test_cannot_cancel_an_already_cancelled_booking(): void
    {
        $booking = Booking::confirm('booking-1', 'session-1', 'user-1', 2, Money::fromCents(4000));
        $booking->cancel();

        $this->expectException(BookingAlreadyCancelledException::class);

        $booking->cancel();
    }
}
