<?php

declare(strict_types=1);

namespace App\Domain\Notification;

use App\Domain\Booking;

interface NotificationSender
{
    public function sendBookingConfirmed(Booking $booking): void;

    public function sendBookingCancelled(Booking $booking): void;
}
