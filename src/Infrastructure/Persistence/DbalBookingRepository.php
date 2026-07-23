<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Booking;
use App\Domain\BookingStatus;
use App\Domain\Money;
use App\Domain\Repository\BookingRepository;
use Doctrine\DBAL\Connection;

final class DbalBookingRepository implements BookingRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function ofId(string $id): ?Booking
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, session_id, user_id, seats, total_price_cents, status FROM booking WHERE id = :id',
            ['id' => $id],
        );

        return false === $row ? null : self::hydrate($row);
    }

    /**
     * Also used to persist a cancellation (an existing booking, same id,
     * only its status changes) — ON DUPLICATE KEY UPDATE covers both the
     * initial insert and that later status update with one statement.
     */
    public function save(Booking $booking): void
    {
        $this->connection->executeStatement(
            'INSERT INTO booking (id, session_id, user_id, seats, total_price_cents, status)
             VALUES (:id, :session_id, :user_id, :seats, :total_price_cents, :status)
             ON DUPLICATE KEY UPDATE status = VALUES(status)',
            [
                'id' => $booking->id(),
                'session_id' => $booking->sessionId(),
                'user_id' => $booking->userId(),
                'seats' => $booking->seats(),
                'total_price_cents' => $booking->totalPrice()->amountInCents(),
                'status' => $booking->status()->value,
            ],
        );
    }

    public function findBySessionId(string $sessionId): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, session_id, user_id, seats, total_price_cents, status FROM booking
             WHERE session_id = :session_id
             ORDER BY created_at',
            ['session_id' => $sessionId],
        );

        return array_map(self::hydrate(...), $rows);
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): Booking
    {
        return Booking::reconstitute(
            (string) $row['id'],
            (string) $row['session_id'],
            (string) $row['user_id'],
            (int) $row['seats'],
            Money::fromCents((int) $row['total_price_cents']),
            BookingStatus::from((string) $row['status']),
        );
    }
}
