<?php

declare(strict_types=1);

namespace App\Tests\Application\InMemory;

use App\Domain\Repository\SessionRepository;
use App\Domain\Session;

/**
 * Emulates the atomic reserveSeats()/releaseSeats() contract by mutating the
 * in-memory Session directly. It's single-threaded (no real concurrency to
 * guard against), so this is NOT proof that overbooking is prevented — that
 * is only demonstrated against the real MySQL adapter (see
 * tests/Concurrency). It only proves the Application service calls the port
 * correctly and reacts to its result.
 */
final class InMemorySessionRepository implements SessionRepository
{
    /** @var array<string, Session> */
    private array $sessions = [];

    public function ofId(string $id): ?Session
    {
        return $this->sessions[$id] ?? null;
    }

    public function save(Session $session): void
    {
        $this->sessions[$session->id()] = $session;
    }

    public function existsForExperienceOnDate(string $experienceId, \DateTimeImmutable $date): bool
    {
        foreach ($this->sessions as $session) {
            if ($session->experienceId() === $experienceId
                && $session->date()->format('Y-m-d') === $date->format('Y-m-d')
            ) {
                return true;
            }
        }

        return false;
    }

    public function reserveSeats(string $sessionId, int $seats, \DateTimeImmutable $now): bool
    {
        $session = $this->ofId($sessionId);

        if (null === $session || $session->hasStartedAt($now) || $seats > $session->availableSeats()) {
            return false;
        }

        $session->decreaseAvailableSeats($seats);

        return true;
    }

    public function releaseSeats(string $sessionId, int $seats): void
    {
        $this->ofId($sessionId)?->increaseAvailableSeats($seats);
    }
}
