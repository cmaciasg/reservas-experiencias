<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;

final class ExperienceControllerTest extends ApiTestCase
{
    #[Test]
    public function creates_and_retrieves_an_experience(): void
    {
        $this->requestJson('POST', '/api/experiences', [
            'provider_id' => 'provider-1',
            'title' => 'City Bike Tour',
            'description' => 'A guided bike tour.',
        ]);

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $created = $this->jsonResponse();
        self::assertSame('provider-1', $created['provider_id']);
        self::assertNotEmpty($created['id']);

        $this->client->request('GET', '/api/experiences/'.$created['id']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame($created, $this->jsonResponse());
    }

    #[Test]
    public function returns_404_for_an_unknown_experience(): void
    {
        $this->client->request('GET', '/api/experiences/does-not-exist');

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    #[Test]
    public function rejects_an_experience_without_a_title(): void
    {
        $this->requestJson('POST', '/api/experiences', [
            'provider_id' => 'provider-1',
            'title' => '',
            'description' => 'A guided bike tour.',
        ]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
    }
}
