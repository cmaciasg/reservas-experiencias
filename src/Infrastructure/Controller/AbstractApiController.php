<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Application\Exception\BookingNotFoundException;
use App\Application\Exception\ExperienceNotFoundException;
use App\Application\Exception\SessionNotFoundException;
use App\Domain\Exception\BookingAlreadyCancelledException;
use App\Domain\Exception\CancellationWindowExpiredException;
use App\Domain\Exception\DuplicateSessionDateException;
use App\Domain\Exception\NotEnoughSeatsAvailableException;
use App\Domain\Exception\PastSessionDateException;
use App\Domain\Exception\SessionAlreadyStartedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Shared JSON request/error handling for the API controllers. No
 * symfony/serializer or symfony/validator: payloads are small and the
 * mapping is straightforward, so manual json_decode/JsonResponse keeps full
 * control over the snake_case wire format without extra dependencies or
 * configuration.
 */
abstract class AbstractApiController
{
    /**
     * @return array<string, mixed>
     */
    protected function payload(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            throw new \InvalidArgumentException('Request body must be a JSON object.');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function dateFrom(array $payload, string $key = 'date'): \DateTimeImmutable
    {
        $value = $payload[$key] ?? null;

        if (!\is_string($value) || '' === $value) {
            throw new \InvalidArgumentException(sprintf('"%s" must be a non-empty date string.', $key));
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid date.', $key));
        }
    }

    /**
     * Runs $action, turning known Application/Domain exceptions into the
     * matching HTTP JSON error response.
     */
    protected function handle(callable $action, int $successStatus = 200): JsonResponse
    {
        try {
            $result = $action();
        } catch (ExperienceNotFoundException|SessionNotFoundException|BookingNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 404);
        } catch (
            DuplicateSessionDateException|
            NotEnoughSeatsAvailableException|
            SessionAlreadyStartedException|
            BookingAlreadyCancelledException|
            CancellationWindowExpiredException $e
        ) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        } catch (PastSessionDateException|\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        return $result instanceof JsonResponse ? $result : new JsonResponse($result, $successStatus);
    }
}
