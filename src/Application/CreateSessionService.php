<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Exception\ExperienceNotFoundException;
use App\Domain\Exception\DuplicateSessionDateException;
use App\Domain\IdGenerator;
use App\Domain\Money;
use App\Domain\Repository\ExperienceRepository;
use App\Domain\Repository\SessionRepository;
use App\Domain\Session;
use Psr\Clock\ClockInterface;

final class CreateSessionService
{
    public function __construct(
        private readonly ExperienceRepository $experiences,
        private readonly SessionRepository $sessions,
        private readonly IdGenerator $idGenerator,
        private readonly ClockInterface $clock,
    ) {
    }

    public function create(string $experienceId, \DateTimeImmutable $date, int $capacity, Money $price): Session
    {
        if (null === $this->experiences->ofId($experienceId)) {
            throw new ExperienceNotFoundException(sprintf('Experience "%s" not found.', $experienceId));
        }

        if ($this->sessions->existsForExperienceOnDate($experienceId, $date)) {
            throw new DuplicateSessionDateException(
                sprintf('Experience "%s" already has a session scheduled on %s.', $experienceId, $date->format('Y-m-d')),
            );
        }

        $session = Session::schedule(
            $this->idGenerator->generate(),
            $experienceId,
            $date,
            $capacity,
            $price,
            $this->clock->now(),
        );

        $this->sessions->save($session);

        return $session;
    }
}
