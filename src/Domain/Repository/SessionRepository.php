<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Session;

interface SessionRepository
{
    public function ofId(string $id): ?Session;

    public function save(Session $session): void;

    public function existsForExperienceOnDate(string $experienceId, \DateTimeImmutable $date): bool;

    /**
     * Atomically reserves $seats on the session: decrements available seats
     * only if there are enough left AND the session hasn't started yet.
     * Must be implemented as a single conditional UPDATE at the persistence
     * layer (no read-then-write) — that's what makes it safe under
     * concurrent requests. Returns whether the reservation succeeded.
     */
    public function reserveSeats(string $sessionId, int $seats, \DateTimeImmutable $now): bool;

    /**
     * Atomically releases $seats back to the session (booking cancellation).
     */
    public function releaseSeats(string $sessionId, int $seats): void;
}
