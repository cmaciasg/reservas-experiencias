<?php

declare(strict_types=1);

namespace App\Tests\Domain;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function test_phpunit_is_wired_up(): void
    {
        self::assertTrue(true);
    }
}
