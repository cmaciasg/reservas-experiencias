<?php

declare(strict_types=1);

namespace App\Infrastructure\Notification;

use App\Domain\Booking;
use App\Domain\Notification\NotificationSender;
use Psr\Log\LoggerInterface;

/**
 * Stand-in for a real email provider: the enunciado only asks to plan the
 * code for sending the email, not to send one for real. Logs what would be
 * sent instead. A SmtpNotificationSender implementing the same port is the
 * natural next adapter if real delivery is ever needed — Application code
 * wouldn't change at all.
 */
final class LogNotificationSender implements NotificationSender
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function sendBookingConfirmed(Booking $booking): void
    {
        $this->logger->info('Booking confirmation email would be sent.', [
            'booking_id' => $booking->id(),
            'session_id' => $booking->sessionId(),
            'user_id' => $booking->userId(),
            'seats' => $booking->seats(),
        ]);
    }

    public function sendBookingCancelled(Booking $booking): void
    {
        $this->logger->info('Booking cancellation email would be sent.', [
            'booking_id' => $booking->id(),
            'session_id' => $booking->sessionId(),
            'user_id' => $booking->userId(),
        ]);
    }
}
