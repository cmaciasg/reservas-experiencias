<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Application\Exception\SessionNotFoundException;
use App\Application\CreateSessionService;
use App\Domain\Money;
use App\Domain\Repository\SessionRepository;
use App\Domain\Session;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SessionController extends AbstractApiController
{
    public function __construct(
        private readonly CreateSessionService $createSession,
        private readonly SessionRepository $sessions,
    ) {
    }

    #[Route('/api/experiences/{experienceId}/sessions', methods: ['POST'])]
    public function create(string $experienceId, Request $request): JsonResponse
    {
        return $this->handle(function () use ($experienceId, $request) {
            $payload = $this->payload($request);

            $session = $this->createSession->create(
                $experienceId,
                $this->dateFrom($payload),
                (int) ($payload['capacity'] ?? 0),
                Money::fromCents((int) ($payload['price_cents'] ?? 0)),
            );

            return self::toArray($session);
        }, 201);
    }

    #[Route('/api/sessions/{id}', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return $this->handle(function () use ($id) {
            $session = $this->sessions->ofId($id);

            if (null === $session) {
                throw new SessionNotFoundException(sprintf('Session "%s" not found.', $id));
            }

            return self::toArray($session);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function toArray(Session $session): array
    {
        return [
            'id' => $session->id(),
            'experience_id' => $session->experienceId(),
            'date' => $session->date()->format(DATE_ATOM),
            'capacity' => $session->capacity(),
            'available_seats' => $session->availableSeats(),
            'price_cents' => $session->price()->amountInCents(),
        ];
    }
}
