<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Money;
use App\Domain\Repository\SessionRepository;
use App\Domain\Session;
use Doctrine\DBAL\Connection;

final class DbalSessionRepository implements SessionRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function ofId(string $id): ?Session
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, experience_id, start_date, capacity, available_seats, price_cents
             FROM session WHERE id = :id',
            ['id' => $id],
        );

        return false === $row ? null : self::hydrate($row);
    }

    public function save(Session $session): void
    {
        $this->connection->executeStatement(
            'INSERT INTO session (id, experience_id, start_date, capacity, available_seats, price_cents)
             VALUES (:id, :experience_id, :start_date, :capacity, :available_seats, :price_cents)',
            [
                'id' => $session->id(),
                'experience_id' => $session->experienceId(),
                'start_date' => $session->date()->format('Y-m-d H:i:s'),
                'capacity' => $session->capacity(),
                'available_seats' => $session->availableSeats(),
                'price_cents' => $session->price()->amountInCents(),
            ],
        );
    }

    public function existsForExperienceOnDate(string $experienceId, \DateTimeImmutable $date): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM session WHERE experience_id = :experience_id AND session_date = :date',
            ['experience_id' => $experienceId, 'date' => $date->format('Y-m-d')],
        );

        return ((int) $count) > 0;
    }

    /**
     * The only operation that's actually safe under concurrent booking
     * requests: a single conditional UPDATE, not a read-then-write. InnoDB's
     * row locking serializes concurrent attempts on the same session, so at
     * most one request can decrement into the last available seats.
     */
    public function reserveSeats(string $sessionId, int $seats, \DateTimeImmutable $now): bool
    {
        $affectedRows = $this->connection->executeStatement(
            'UPDATE session
             SET available_seats = available_seats - :seats
             WHERE id = :id AND available_seats >= :seats AND start_date > :now',
            [
                'seats' => $seats,
                'id' => $sessionId,
                'now' => $now->format('Y-m-d H:i:s'),
            ],
        );

        return $affectedRows > 0;
    }

    public function releaseSeats(string $sessionId, int $seats): void
    {
        $this->connection->executeStatement(
            'UPDATE session
             SET available_seats = LEAST(capacity, available_seats + :seats)
             WHERE id = :id',
            ['seats' => $seats, 'id' => $sessionId],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): Session
    {
        return Session::reconstitute(
            (string) $row['id'],
            (string) $row['experience_id'],
            new \DateTimeImmutable((string) $row['start_date']),
            (int) $row['capacity'],
            (int) $row['available_seats'],
            Money::fromCents((int) $row['price_cents']),
        );
    }
}
