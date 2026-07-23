<?php

declare(strict_types=1);

namespace App\Tests\Application\InMemory;

use App\Domain\Experience;
use App\Domain\Repository\ExperienceRepository;

final class InMemoryExperienceRepository implements ExperienceRepository
{
    /** @var array<string, Experience> */
    private array $experiences = [];

    public function ofId(string $id): ?Experience
    {
        return $this->experiences[$id] ?? null;
    }

    public function save(Experience $experience): void
    {
        $this->experiences[$experience->id()] = $experience;
    }
}
