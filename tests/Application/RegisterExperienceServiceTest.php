<?php

declare(strict_types=1);

namespace App\Tests\Application;

use App\Application\RegisterExperienceService;
use App\Tests\Application\InMemory\InMemoryExperienceRepository;
use App\Tests\Application\InMemory\SequentialIdGenerator;
use PHPUnit\Framework\TestCase;

final class RegisterExperienceServiceTest extends TestCase
{
    public function test_registers_and_persists_an_experience(): void
    {
        $experiences = new InMemoryExperienceRepository();
        $service = new RegisterExperienceService($experiences, new SequentialIdGenerator());

        $experience = $service->register('provider-1', 'City Bike Tour', 'A guided bike tour.');

        self::assertSame($experience, $experiences->ofId($experience->id()));
        self::assertSame('provider-1', $experience->providerId());
    }
}
