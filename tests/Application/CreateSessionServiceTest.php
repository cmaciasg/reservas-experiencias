<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\CreateSessionService;
use App\Application\Exception\ExperienceNotFoundException;
use App\Domain\Exception\DuplicateSessionDateException;
use App\Domain\Exception\PastSessionDateException;
use App\Domain\Experience;
use App\Domain\Money;
use App\Tests\Application\InMemory\InMemoryExperienceRepository;
use App\Tests\Application\InMemory\InMemorySessionRepository;
use App\Tests\Application\InMemory\SequentialIdGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CreateSessionServiceTest extends TestCase
{
    private InMemoryExperienceRepository $experiences;
    private InMemorySessionRepository $sessions;
    private MockClock $clock;
    private CreateSessionService $service;

    protected function setUp(): void
    {
        $this->experiences = new InMemoryExperienceRepository();
        $this->sessions = new InMemorySessionRepository();
        $this->clock = new MockClock('2026-08-01 10:00:00');
        $this->service = new CreateSessionService(
            $this->experiences,
            $this->sessions,
            new SequentialIdGenerator(),
            $this->clock,
        );

        $this->experiences->save(Experience::register('exp-1', 'provider-1', 'City Bike Tour', 'Desc'));
    }

    public function test_creates_a_session_for_an_existing_experience(): void
    {
        $session = $this->service->create('exp-1', $this->clock->now()->modify('+1 day'), 20, Money::fromCents(2000));

        self::assertSame('exp-1', $session->experienceId());
        self::assertSame(20, $session->availableSeats());
        self::assertSame($session, $this->sessions->ofId($session->id()));
    }

    public function test_rejects_a_session_for_a_non_existing_experience(): void
    {
        $this->expectException(ExperienceNotFoundException::class);

        $this->service->create('exp-unknown', $this->clock->now()->modify('+1 day'), 20, Money::fromCents(2000));
    }

    public function test_rejects_a_second_session_for_the_same_experience_on_the_same_day(): void
    {
        $date = $this->clock->now()->modify('+1 day');
        $this->service->create('exp-1', $date, 20, Money::fromCents(2000));

        $this->expectException(DuplicateSessionDateException::class);

        // Same calendar day, different time of day.
        $this->service->create('exp-1', $date->modify('+3 hours'), 10, Money::fromCents(1500));
    }

    public function test_allows_a_second_session_for_the_same_experience_on_a_different_day(): void
    {
        $date = $this->clock->now()->modify('+1 day');
        $this->service->create('exp-1', $date, 20, Money::fromCents(2000));

        $second = $this->service->create('exp-1', $date->modify('+1 day'), 10, Money::fromCents(1500));

        self::assertSame(10, $second->availableSeats());
    }

    public function test_rejects_a_session_scheduled_in_the_past(): void
    {
        $this->expectException(PastSessionDateException::class);

        $this->service->create('exp-1', $this->clock->now()->modify('-1 day'), 20, Money::fromCents(2000));
    }
}
