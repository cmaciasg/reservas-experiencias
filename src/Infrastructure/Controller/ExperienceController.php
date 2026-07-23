<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Application\Exception\ExperienceNotFoundException;
use App\Application\RegisterExperienceService;
use App\Domain\Experience;
use App\Domain\Repository\ExperienceRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/experiences')]
final class ExperienceController extends AbstractApiController
{
    public function __construct(
        private readonly RegisterExperienceService $registerExperience,
        private readonly ExperienceRepository $experiences,
    ) {
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        return $this->handle(function () use ($request) {
            $payload = $this->payload($request);

            $experience = $this->registerExperience->register(
                providerId: (string) ($payload['provider_id'] ?? ''),
                title: (string) ($payload['title'] ?? ''),
                description: (string) ($payload['description'] ?? ''),
            );

            return self::toArray($experience);
        }, 201);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return $this->handle(function () use ($id) {
            $experience = $this->experiences->ofId($id);

            if (null === $experience) {
                throw new ExperienceNotFoundException(sprintf('Experience "%s" not found.', $id));
            }

            return self::toArray($experience);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function toArray(Experience $experience): array
    {
        return [
            'id' => $experience->id(),
            'provider_id' => $experience->providerId(),
            'title' => $experience->title(),
            'description' => $experience->description(),
        ];
    }
}
