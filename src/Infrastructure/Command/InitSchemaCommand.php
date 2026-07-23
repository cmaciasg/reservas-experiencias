<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * No doctrine/migrations: this is a fixed, small schema for a technical
 * exercise, not a production system with an evolving one. A single
 * idempotent "CREATE TABLE IF NOT EXISTS" script is enough (same choice as
 * the Visiotech exercise's app:db:init).
 */
#[AsCommand(name: 'app:db:init', description: 'Creates the database schema (idempotent)')]
final class InitSchemaCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sql = file_get_contents(__DIR__.'/../Persistence/schema.sql');

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $this->connection->executeStatement($statement);
        }

        $output->writeln('<info>Schema created (or already up to date).</info>');

        return Command::SUCCESS;
    }
}
