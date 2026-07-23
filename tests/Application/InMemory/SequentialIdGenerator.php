<?php

declare(strict_types=1);

namespace App\Tests\Application\InMemory;

use App\Domain\IdGenerator;

final class SequentialIdGenerator implements IdGenerator
{
    private int $next = 1;

    public function generate(): string
    {
        return sprintf('id-%d', $this->next++);
    }
}
