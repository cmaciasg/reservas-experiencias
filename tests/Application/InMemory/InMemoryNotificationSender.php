<?php

declare(strict_types=1);

namespace App\Tests\Application\InMemory;

use App\Domain\Booking;
use App\Domain\Notification\NotificationSender;

final class InMemoryNotificationSender implements NotificationSender
{
    /** @var list<Booking> */
    private array $bookingConfirmedNotifications = [];

    /** @var list<Booking> */
    private array $bookingCancelledNotifications = [];

    public function sendBookingConfirmed(Booking $booking): void
    {
        $this->bookingConfirmedNotifications[] = $booking;
    }

    public function sendBookingCancelled(Booking $booking): void
    {
        $this->bookingCancelledNotifications[] = $booking;
    }

    public function confirmedNotificationsCount(): int
    {
        return count($this->bookingConfirmedNotifications);
    }

    public function cancelledNotificationsCount(): int
    {
        return count($this->bookingCancelledNotifications);
    }
}
