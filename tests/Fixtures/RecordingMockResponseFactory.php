<?php

declare(strict_types=1);

namespace Netresearch\AssetPickerBundle\Tests\Fixtures;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Mock response factory for framework.http_client: answers with the queued
 * responses and records every outgoing request.
 */
final class RecordingMockResponseFactory
{
    /** @var list<MockResponse> */
    private array $queue = [];

    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    public array $requests = [];

    public function enqueue(MockResponse $response): void
    {
        $this->queue[] = $response;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

        return array_shift($this->queue) ?? throw new \LogicException(sprintf('Unexpected request: %s %s', $method, $url));
    }
}
