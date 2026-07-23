<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Experience;
use App\Domain\Repository\ExperienceRepository;
use Doctrine\DBAL\Connection;

final class DbalExperienceRepository implements ExperienceRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function ofId(string $id): ?Experience
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, provider_id, title, description FROM experience WHERE id = :id',
            ['id' => $id],
        );

        return false === $row ? null : self::hydrate($row);
    }

    public function save(Experience $experience): void
    {
        $this->connection->executeStatement(
            'INSERT INTO experience (id, provider_id, title, description)
             VALUES (:id, :provider_id, :title, :description)',
            [
                'id' => $experience->id(),
                'provider_id' => $experience->providerId(),
                'title' => $experience->title(),
                'description' => $experience->description(),
            ],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function hydrate(array $row): Experience
    {
        return Experience::register(
            (string) $row['id'],
            (string) $row['provider_id'],
            (string) $row['title'],
            (string) $row['description'],
        );
    }
}
