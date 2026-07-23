<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exception\NotEnoughSeatsAvailableException;
use App\Domain\Exception\PastSessionDateException;

/**
 * Aggregate root. Does not hold the list of bookings for this session
 * (Booking is its own aggregate, referencing sessionId) — availableSeats is
 * the single source of truth for capacity, kept in sync by the repository.
 */
final class Session
{
    private function __construct(
        private readonly string $id,
        private readonly string $experienceId,
        private readonly \DateTimeImmutable $date,
        private readonly int $capacity,
        private int $availableSeats,
        private readonly Money $price,
    ) {
    }

    public static function schedule(
        string $id,
        string $experienceId,
        \DateTimeImmutable $date,
        int $capacity,
        Money $price,
        \DateTimeImmutable $now,
    ): self {
        if ($capacity <= 0) {
            throw new \InvalidArgumentException('Session capacity must be greater than zero.');
        }

        if ($date < $now) {
            throw new PastSessionDateException('Cannot schedule a session in the past.');
        }

        return new self($id, $experienceId, $date, $capacity, $capacity, $price);
    }

    /**
     * Rebuilds a Session as stored in the database, without re-checking the
     * "not in the past" invariant — that's only enforced at creation time. A
     * session legitimately becomes past as time goes by after being scheduled.
     */
    public static function reconstitute(
        string $id,
        string $experienceId,
        \DateTimeImmutable $date,
        int $capacity,
        int $availableSeats,
        Money $price,
    ): self {
        return new self($id, $experienceId, $date, $capacity, $availableSeats, $price);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function experienceId(): string
    {
        return $this->experienceId;
    }

    public function date(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function availableSeats(): int
    {
        return $this->availableSeats;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function hasStartedAt(\DateTimeImmutable $now): bool
    {
        return $this->date <= $now;
    }

    /**
     * In-memory bookkeeping used by the in-memory repository test double
     * (and to express the rule in the domain model itself). The real MySQL
     * repository does NOT read a Session, mutate it and save it back to
     * decide this — it runs a single atomic conditional UPDATE, which is the
     * only thing that's actually safe under concurrent requests.
     */
    public function decreaseAvailableSeats(int $seats): void
    {
        if ($seats <= 0) {
            throw new \InvalidArgumentException('Seats to reserve must be greater than zero.');
        }

        if ($seats > $this->availableSeats) {
            throw new NotEnoughSeatsAvailableException('Not enough seats available for this session.');
        }

        $this->availableSeats -= $seats;
    }

    public function increaseAvailableSeats(int $seats): void
    {
        if ($seats <= 0) {
            throw new \InvalidArgumentException('Seats to release must be greater than zero.');
        }

        $this->availableSeats = min($this->capacity, $this->availableSeats + $seats);
    }
}
