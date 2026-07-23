<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Experience;
use App\Domain\IdGenerator;
use App\Domain\Repository\ExperienceRepository;

final class RegisterExperienceService
{
    public function __construct(
        private readonly ExperienceRepository $experiences,
        private readonly IdGenerator $idGenerator,
    ) {
    }

    public function register(string $providerId, string $title, string $description): Experience
    {
        $experience = Experience::register($this->idGenerator->generate(), $providerId, $title, $description);

        $this->experiences->save($experience);

        return $experience;
    }
}
