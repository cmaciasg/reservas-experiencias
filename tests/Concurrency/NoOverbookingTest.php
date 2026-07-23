<?php

declare(strict_types=1);

namespace App\Tests\Concurrency;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The central concern of the exercise: many simultaneous booking requests
 * for a session with limited capacity must never oversell it. A single
 * PHP process can't produce real concurrency (PHPUnit runs sequentially),
 * so this spawns many independent OS processes (see
 * reserve-seats-worker.php) that each call the real
 * DbalSessionRepository::reserveSeats() against the same MySQL row at
 * (as close as the OS scheduler allows to) the same time.
 */
final class NoOverbookingTest extends KernelTestCase
{
    private const SESSION_ID = 'session-concurrency';
    private const CAPACITY = 5;
    private const CONCURRENT_REQUESTS = 20;

    #[Test]
    public function concurrent_bookings_never_oversell_a_session_with_limited_capacity(): void
    {
        $connection = $this->prepareSessionWithLimitedCapacity();
        self::ensureKernelShutdown();

        $results = $this->reserveOneSeatConcurrently(self::CONCURRENT_REQUESTS);

        $successCount = \count(array_filter($results, static fn (string $r) => '1' === $r));

        self::assertSame(
            min(self::CAPACITY, self::CONCURRENT_REQUESTS),
            $successCount,
            'Exactly as many concurrent requests as there were seats should have succeeded — no more, no less.',
        );

        $remainingSeats = (int) $connection->fetchOne(
            'SELECT available_seats FROM session WHERE id = :id',
            ['id' => self::SESSION_ID],
        );

        self::assertSame(self::CAPACITY - $successCount, $remainingSeats);
        self::assertGreaterThanOrEqual(0, $remainingSeats, 'Available seats must never go negative (overbooking).');
    }

    private function prepareSessionWithLimitedCapacity(): Connection
    {
        self::bootKernel();
        $connection = static::getContainer()->get(Connection::class);

        $schemaPath = \dirname(__DIR__, 2).'/src/Infrastructure/Persistence/schema.sql';
        foreach (array_filter(array_map('trim', explode(';', file_get_contents($schemaPath)))) as $statement) {
            $connection->executeStatement($statement);
        }
        foreach (['booking', 'session', 'experience'] as $table) {
            $connection->executeStatement("DELETE FROM {$table}");
        }

        $connection->executeStatement(
            'INSERT INTO experience (id, provider_id, title, description) VALUES (:id, :provider_id, :title, :description)',
            ['id' => 'experience-concurrency', 'provider_id' => 'provider-1', 'title' => 'Test', 'description' => 'Test'],
        );

        $connection->executeStatement(
            'INSERT INTO session (id, experience_id, start_date, capacity, available_seats, price_cents)
             VALUES (:id, :experience_id, :start_date, :capacity, :available_seats, :price_cents)',
            [
                'id' => self::SESSION_ID,
                'experience_id' => 'experience-concurrency',
                'start_date' => (new \DateTimeImmutable('+3 days'))->format('Y-m-d H:i:s'),
                'capacity' => self::CAPACITY,
                'available_seats' => self::CAPACITY,
                'price_cents' => 1000,
            ],
        );

        return $connection;
    }

    /**
     * @return list<string>
     */
    private function reserveOneSeatConcurrently(int $numberOfProcesses): array
    {
        $workerScript = __DIR__.'/reserve-seats-worker.php';
        $descriptorSpec = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        $processes = [];
        $pipes = [];
        for ($i = 0; $i < $numberOfProcesses; ++$i) {
            $processPipes = [];
            $process = proc_open(['php', $workerScript, self::SESSION_ID, '1'], $descriptorSpec, $processPipes);
            self::assertIsResource($process, 'Failed to spawn a worker process for the concurrency test.');
            $processes[] = $process;
            $pipes[] = $processPipes;
        }

        $results = [];
        foreach ($processes as $i => $process) {
            $results[] = trim(stream_get_contents($pipes[$i][1]));
            $stderr = stream_get_contents($pipes[$i][2]);
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);
            $exitCode = proc_close($process);

            self::assertSame(0, $exitCode, "Worker process failed:\n{$stderr}");
        }

        return $results;
    }
}
