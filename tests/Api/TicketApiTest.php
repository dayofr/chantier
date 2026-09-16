<?php

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

final class TicketApiTest extends ApiTestCase
{
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    public function testCrudUsesKeysAsIdentifiers(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/projects', ['json' => ['key' => 'AETH', 'name' => 'Aether']]);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/tickets', ['json' => ['project' => '/api/projects/AETH', 'title' => 'Premier', 'priority' => 'high']]);
        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['@id' => '/api/tickets/AETH-1', 'key' => 'AETH-1', 'status' => 'todo', 'project' => '/api/projects/AETH']);

        $client->request('PATCH', '/api/tickets/AETH-1', [
            'json' => ['status' => 'in_progress'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/activities?ticket=AETH-1');
        self::assertJsonContains(['totalItems' => 2, 'member' => [['type' => 'status_changed', 'data' => ['from' => 'todo', 'to' => 'in_progress']]]]);

        $client->request('GET', '/api/tickets?project=AETH&status=in_progress');
        self::assertJsonContains(['totalItems' => 1]);
    }

    public function testValidation(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/projects', ['json' => ['key' => 'x', 'name' => '']]);
        self::assertResponseStatusCodeSame(422);
    }
}
