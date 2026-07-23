<?php

declare(strict_types=1);

/**
 * Standalone worker process: boots the real Symfony test container and
 * attempts to reserve seats through the actual production code path
 * (DbalSessionRepository::reserveSeats(), the atomic conditional UPDATE).
 *
 * Run as an independent OS process (via proc_open, see NoOverbookingTest) so
 * that many of these genuinely execute at the same time against MySQL —
 * that's the only way to actually exercise the race condition this is
 * meant to prevent. Prints "1" (reserved) or "0" (rejected) to stdout.
 */

use App\Domain\Repository\SessionRepository;
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');

[, $sessionId, $seats] = $argv;

$kernel = new Kernel('test', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

$container = $kernel->getContainer()->get('test.service_container');
$sessions = $container->get(SessionRepository::class);

$reserved = $sessions->reserveSeats($sessionId, (int) $seats, new \DateTimeImmutable());

echo $reserved ? '1' : '0';
