<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exception\BookingAlreadyCancelledException;

final class Booking
{
    private function __construct(
        private readonly string $id,
        private readonly string $sessionId,
        private readonly string $userId,
        private readonly int $seats,
        private readonly Money $totalPrice,
        private BookingStatus $status,
    ) {
    }

    public static function confirm(string $id, string $sessionId, string $userId, int $seats, Money $totalPrice): self
    {
        if ($seats <= 0) {
            throw new \InvalidArgumentException('A booking must reserve at least one seat.');
        }

        if (trim($userId) === '') {
            throw new \InvalidArgumentException('Booking must reference a user.');
        }

        return new self($id, $sessionId, $userId, $seats, $totalPrice, BookingStatus::Confirmed);
    }

    public static function reconstitute(
        string $id,
        string $sessionId,
        string $userId,
        int $seats,
        Money $totalPrice,
        BookingStatus $status,
    ): self {
        return new self($id, $sessionId, $userId, $seats, $totalPrice, $status);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function sessionId(): string
    {
        return $this->sessionId;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function seats(): int
    {
        return $this->seats;
    }

    public function totalPrice(): Money
    {
        return $this->totalPrice;
    }

    public function status(): BookingStatus
    {
        return $this->status;
    }

    public function isCancelled(): bool
    {
        return BookingStatus::Cancelled === $this->status;
    }

    public function cancel(): void
    {
        if ($this->isCancelled()) {
            throw new BookingAlreadyCancelledException('This booking is already cancelled.');
        }

        $this->status = BookingStatus::Cancelled;
    }
}
