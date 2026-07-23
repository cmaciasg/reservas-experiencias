<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;

final class SessionControllerTest extends ApiTestCase
{
    private function createExperience(): array
    {
        $this->requestJson('POST', '/api/experiences', [
            'provider_id' => 'provider-1',
            'title' => 'City Bike Tour',
            'description' => 'A guided bike tour.',
        ]);

        return $this->jsonResponse();
    }

    #[Test]
    public function creates_and_retrieves_a_session(): void
    {
        $experience = $this->createExperience();
        $date = (new \DateTimeImmutable('+3 days 18:00'))->format(DATE_ATOM);

        $this->requestJson('POST', "/api/experiences/{$experience['id']}/sessions", [
            'date' => $date,
            'capacity' => 10,
            'price_cents' => 1500,
        ]);

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $created = $this->jsonResponse();
        self::assertSame(10, $created['capacity']);
        self::assertSame(10, $created['available_seats']);

        $this->client->request('GET', '/api/sessions/'.$created['id']);

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame($created, $this->jsonResponse());
    }

    #[Test]
    public function returns_404_when_the_experience_does_not_exist(): void
    {
        $date = (new \DateTimeImmutable('+3 days'))->format(DATE_ATOM);

        $this->requestJson('POST', '/api/experiences/does-not-exist/sessions', [
            'date' => $date,
            'capacity' => 10,
            'price_cents' => 1500,
        ]);

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    #[Test]
    public function rejects_a_second_session_on_the_same_day(): void
    {
        $experience = $this->createExperience();
        $date = new \DateTimeImmutable('+3 days 18:00');

        $this->requestJson('POST', "/api/experiences/{$experience['id']}/sessions", [
            'date' => $date->format(DATE_ATOM),
            'capacity' => 10,
            'price_cents' => 1500,
        ]);
        self::assertSame(201, $this->client->getResponse()->getStatusCode());

        $this->requestJson('POST', "/api/experiences/{$experience['id']}/sessions", [
            'date' => $date->modify('+3 hours')->format(DATE_ATOM),
            'capacity' => 5,
            'price_cents' => 1000,
        ]);

        self::assertSame(409, $this->client->getResponse()->getStatusCode());
    }

    #[Test]
    public function rejects_a_session_in_the_past(): void
    {
        $experience = $this->createExperience();

        $this->requestJson('POST', "/api/experiences/{$experience['id']}/sessions", [
            'date' => (new \DateTimeImmutable('-1 day'))->format(DATE_ATOM),
            'capacity' => 10,
            'price_cents' => 1500,
        ]);

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
    }
}
