<?php

namespace App\Tests\Mcp;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Parle au serveur MCP comme un vrai client : initialize, puis tools/call.
 */
abstract class McpTestCase extends WebTestCase
{
    use ResetDatabase;

    protected KernelBrowser $client;
    private ?string $sessionId = null;
    private int $requestId = 0;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $response = $this->rpc('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => new \stdClass(),
            'clientInfo' => ['name' => 'phpunit', 'version' => '1'],
        ]);
        self::assertArrayHasKey('result', $response);
        $this->sessionId = $this->client->getResponse()->headers->get('Mcp-Session-Id');
        self::assertNotNull($this->sessionId);
        $this->send(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);
    }

    /** Appelle un outil et renvoie le JSON décodé. Échoue si l'outil renvoie une erreur. */
    protected function callTool(string $name, array $arguments = []): array
    {
        $result = $this->callToolRaw($name, $arguments);
        self::assertFalse($result['isError'] ?? false, $result['content'][0]['text'] ?? 'Erreur outil');

        return json_decode($result['content'][0]['text'], true, flags: \JSON_THROW_ON_ERROR);
    }

    /** Appelle un outil censé échouer et renvoie le message d'erreur. */
    protected function callToolError(string $name, array $arguments = []): string
    {
        $response = $this->rpc('tools/call', ['name' => $name, 'arguments' => (object) $arguments], raw: true);
        if (isset($response['error'])) {
            return $response['error']['message'];
        }
        self::assertTrue($response['result']['isError'] ?? false, 'L\'outil aurait dû échouer.');

        return $response['result']['content'][0]['text'];
    }

    protected function listTools(): array
    {
        return array_column($this->rpc('tools/list')['result']['tools'], null, 'name');
    }

    private function callToolRaw(string $name, array $arguments): array
    {
        $response = $this->rpc('tools/call', ['name' => $name, 'arguments' => (object) $arguments]);
        self::assertArrayHasKey('result', $response, json_encode($response['error'] ?? $response, \JSON_UNESCAPED_UNICODE));

        return $response['result'];
    }

    private function rpc(string $method, array $params = [], bool $raw = false): array
    {
        return $this->send(['jsonrpc' => '2.0', 'id' => ++$this->requestId, 'method' => $method, 'params' => (object) $params]);
    }

    private function send(array $payload): array
    {
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'];
        if (null !== $this->sessionId) {
            $headers['HTTP_MCP_SESSION_ID'] = $this->sessionId;
        }
        $this->client->request('POST', '/mcp', server: $headers, content: json_encode($payload));
        $content = $this->client->getResponse()->getContent();

        return '' === $content ? [] : json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }
}
