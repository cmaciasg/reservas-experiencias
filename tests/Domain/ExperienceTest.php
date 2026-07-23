<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use App\Domain\Experience;
use PHPUnit\Framework\TestCase;

final class ExperienceTest extends TestCase
{
    public function test_registers_an_experience_with_valid_data(): void
    {
        $experience = Experience::register('exp-1', 'provider-1', 'City Bike Tour', 'A guided bike tour.');

        self::assertSame('exp-1', $experience->id());
        self::assertSame('provider-1', $experience->providerId());
        self::assertSame('City Bike Tour', $experience->title());
        self::assertSame('A guided bike tour.', $experience->description());
    }

    public function test_rejects_an_empty_title(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Experience::register('exp-1', 'provider-1', '   ', 'A guided bike tour.');
    }

    public function test_rejects_a_missing_provider(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Experience::register('exp-1', '', 'City Bike Tour', 'A guided bike tour.');
    }
}
