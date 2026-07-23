<?php

declare(strict_types=1);

namespace App\Tests\Application\InMemory;

use App\Domain\Booking;
use App\Domain\Repository\BookingRepository;

final class InMemoryBookingRepository implements BookingRepository
{
    /** @var array<string, Booking> */
    private array $bookings = [];

    public function ofId(string $id): ?Booking
    {
        return $this->bookings[$id] ?? null;
    }

    public function save(Booking $booking): void
    {
        $this->bookings[$booking->id()] = $booking;
    }
}
