<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\IdGenerator;
use Symfony\Component\Uid\Uuid;

final class UuidIdGenerator implements IdGenerator
{
    public function generate(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}
