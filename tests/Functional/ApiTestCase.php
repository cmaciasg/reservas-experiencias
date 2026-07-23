<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base for functional API tests: boots a real client against the dedicated
 * MySQL test database (reservas_experiencias_test, see .env/.env.local),
 * recreating the schema and wiping all tables before each test for
 * isolation.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected Connection $connection;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->connection = static::getContainer()->get(Connection::class);

        $schemaPath = \dirname(__DIR__, 2).'/src/Infrastructure/Persistence/schema.sql';
        foreach (array_filter(array_map('trim', explode(';', file_get_contents($schemaPath)))) as $statement) {
            $this->connection->executeStatement($statement);
        }

        // Children first: booking -> session -> experience (foreign keys).
        foreach (['booking', 'session', 'experience'] as $table) {
            $this->connection->executeStatement("DELETE FROM {$table}");
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();

        return '' === $content ? [] : json_decode($content, true);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function requestJson(string $method, string $uri, array $payload = []): void
    {
        $this->client->request(
            $method,
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload),
        );
    }
}
