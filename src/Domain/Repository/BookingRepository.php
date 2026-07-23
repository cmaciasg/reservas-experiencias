<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Booking;

interface BookingRepository
{
    public function ofId(string $id): ?Booking;

    public function save(Booking $booking): void;

    /**
     * @return list<Booking>
     */
    public function findBySessionId(string $sessionId): array;
}
