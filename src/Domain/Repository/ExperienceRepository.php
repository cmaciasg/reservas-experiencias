<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Experience;

interface ExperienceRepository
{
    public function ofId(string $id): ?Experience;

    public function save(Experience $experience): void;
}
