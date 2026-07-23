<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Application\CancelBookingService;
use App\Application\CreateBookingService;
use App\Application\Exception\BookingNotFoundException;
use App\Domain\Booking;
use App\Domain\Repository\BookingRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class BookingController extends AbstractApiController
{
    public function __construct(
        private readonly CreateBookingService $createBooking,
        private readonly CancelBookingService $cancelBooking,
        private readonly BookingRepository $bookings,
    ) {
    }

    #[Route('/api/sessions/{sessionId}/bookings', methods: ['POST'])]
    public function create(string $sessionId, Request $request): JsonResponse
    {
        return $this->handle(function () use ($sessionId, $request) {
            $payload = $this->payload($request);

            $booking = $this->createBooking->create(
                $sessionId,
                (string) ($payload['user_id'] ?? ''),
                (int) ($payload['seats'] ?? 0),
            );

            return self::toArray($booking);
        }, 201);
    }

    #[Route('/api/bookings/{id}/cancel', methods: ['POST'])]
    public function cancel(string $id): JsonResponse
    {
        return $this->handle(fn () => self::toArray($this->cancelBooking->cancel($id)));
    }

    #[Route('/api/bookings/{id}', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return $this->handle(function () use ($id) {
            $booking = $this->bookings->ofId($id);

            if (null === $booking) {
                throw new BookingNotFoundException(sprintf('Booking "%s" not found.', $id));
            }

            return self::toArray($booking);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function toArray(Booking $booking): array
    {
        return [
            'id' => $booking->id(),
            'session_id' => $booking->sessionId(),
            'user_id' => $booking->userId(),
            'seats' => $booking->seats(),
            'total_price_cents' => $booking->totalPrice()->amountInCents(),
            'status' => $booking->status()->value,
        ];
    }
}
