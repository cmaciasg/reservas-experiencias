<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Port: aggregates need an id before being persisted (they're constructed
 * whole, e.g. Experience::register(id, ...)), so identity generation can't
 * wait for a database round-trip like an autoincrement column would.
 */
interface IdGenerator
{
    public function generate(): string;
}
