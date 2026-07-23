<?php

declare(strict_types=1);

namespace App\Domain;

final class Experience
{
    private function __construct(
        private readonly string $id,
        private readonly string $providerId,
        private string $title,
        private string $description,
    ) {
    }

    public static function register(string $id, string $providerId, string $title, string $description): self
    {
        if (trim($title) === '') {
            throw new \InvalidArgumentException('Experience title cannot be empty.');
        }

        if (trim($providerId) === '') {
            throw new \InvalidArgumentException('Experience must reference a provider.');
        }

        return new self($id, $providerId, $title, $description);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function providerId(): string
    {
        return $this->providerId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }
}
