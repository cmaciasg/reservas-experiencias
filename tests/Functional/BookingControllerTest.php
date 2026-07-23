<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;

final class BookingControllerTest extends ApiTestCase
{
    private function createSession(int $capacity = 5, string $offset = '+3 days 18:00'): array
    {
        $this->requestJson('POST', '/api/experiences', [
            'provider_id' => 'provider-1',
            'title' => 'City Bike Tour',
            'description' => 'A guided bike tour.',
        ]);
        $experience = $this->jsonResponse();

        $this->requestJson('POST', "/api/experiences/{$experience['id']}/sessions", [
            'date' => (new \DateTimeImmutable($offset))->format(DATE_ATOM),
            'capacity' => $capacity,
            'price_cents' => 2000,
        ]);

        return $this->jsonResponse();
    }

    #[Test]
    public function books_seats_and_decreases_availability(): void
    {
        $session = $this->createSession(capacity: 5);

        $this->requestJson('POST', "/api/sessions/{$session['id']}/bookings", [
            'user_id' => 'user-1',
            'seats' => 2,
        ]);

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        $booking = $this->jsonResponse();
        self::assertSame('confirmed', $booking['status']);
        self::assertSame(4000, $booking['total_price_cents']);

        $this->client->request('GET', '/api/sessions/'.$session['id']);
        self::assertSame(3, $this->jsonResponse()['available_seats']);
    }

    #[Test]
    public function rejects_booking_more_seats_than_available(): void
    {
        $session = $this->createSession(capacity: 1);

        $this->requestJson('POST', "/api/sessions/{$session['id']}/bookings", [
            'user_id' => 'user-1',
            'seats' => 2,
        ]);

        self::assertSame(409, $this->client->getResponse()->getStatusCode());
    }

    #[Test]
    public function returns_404_when_the_session_does_not_exist(): void
    {
        $this->requestJson('POST', '/api/sessions/does-not-exist/bookings', [
            'user_id' => 'user-1',
            'seats' => 1,
        ]);

        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    #[Test]
    public function cancels_a_booking_and_releases_its_seats(): void
    {
        $session = $this->createSession(capacity: 5);
        $this->requestJson('POST', "/api/sessions/{$session['id']}/bookings", ['user_id' => 'user-1', 'seats' => 2]);
        $booking = $this->jsonResponse();

        $this->client->request('POST', "/api/bookings/{$booking['id']}/cancel");

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('cancelled', $this->jsonResponse()['status']);

        $this->client->request('GET', '/api/sessions/'.$session['id']);
        self::assertSame(5, $this->jsonResponse()['available_seats']);
    }

    #[Test]
    public function rejects_cancelling_the_same_booking_twice(): void
    {
        $session = $this->createSession(capacity: 5);
        $this->requestJson('POST', "/api/sessions/{$session['id']}/bookings", ['user_id' => 'user-1', 'seats' => 1]);
        $booking = $this->jsonResponse();

        $this->client->request('POST', "/api/bookings/{$booking['id']}/cancel");
        self::assertSame(200, $this->client->getResponse()->getStatusCode());

        $this->client->request('POST', "/api/bookings/{$booking['id']}/cancel");
        self::assertSame(409, $this->client->getResponse()->getStatusCode());
    }

    #[Test]
    public function rejects_cancelling_less_than_24_hours_before_the_session(): void
    {
        $session = $this->createSession(capacity: 5, offset: '+12 hours');
        $this->requestJson('POST', "/api/sessions/{$session['id']}/bookings", ['user_id' => 'user-1', 'seats' => 1]);
        $booking = $this->jsonResponse();

        $this->client->request('POST', "/api/bookings/{$booking['id']}/cancel");

        self::assertSame(409, $this->client->getResponse()->getStatusCode());
    }
}
