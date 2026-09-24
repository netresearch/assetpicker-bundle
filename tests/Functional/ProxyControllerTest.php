<?php

declare(strict_types=1);

namespace Netresearch\AssetPickerBundle\Tests\Functional;

use Netresearch\AssetPickerBundle\Controller\ProxyController;
use Netresearch\AssetPickerBundle\Tests\Fixtures\RecordingMockResponseFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ProxyController::class)]
final class ProxyControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private RecordingMockResponseFactory $upstream;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $upstream = self::getContainer()->get(RecordingMockResponseFactory::class);
        self::assertInstanceOf(RecordingMockResponseFactory::class, $upstream);
        $this->upstream = $upstream;
    }

    public function testForwardsTheRequestToTheTargetAndReturnsTheUpstreamResponse(): void
    {
        $this->upstream->enqueue(new MockResponse('IMAGE-BYTES', [
            'http_code' => 200,
            'response_headers' => ['content-type' => 'image/png'],
        ]));

        $this->client->request('GET', '/assetpicker?to=' . urlencode('https://em.example.org/app/a.png?size=large'));

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('IMAGE-BYTES', $this->client->getInternalResponse()->getContent());
        self::assertSame('image/png', $response->headers->get('content-type'));

        self::assertCount(1, $this->upstream->requests);
        self::assertSame('GET', $this->upstream->requests[0]['method']);
        self::assertSame('https://em.example.org/app/a.png?size=large', $this->upstream->requests[0]['url']);
    }

    public function testForwardsMethodAndBody(): void
    {
        $this->upstream->enqueue(new MockResponse('{"ok":true}', ['http_code' => 201]));

        $this->client->request(
            'POST',
            '/assetpicker?to=' . urlencode('https://em.example.org/app/login'),
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"user":"u"}',
        );

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        self::assertSame('POST', $this->upstream->requests[0]['method']);
        self::assertSame('{"user":"u"}', $this->upstream->requests[0]['options']['body']);
    }

    public function testDoesNotForwardTheApplicationsCookiesAndCredentials(): void
    {
        $this->upstream->enqueue(new MockResponse('{}', ['http_code' => 200]));

        $this->client->request(
            'GET',
            '/assetpicker?to=' . urlencode('https://em.example.org/app/a.json'),
            server: [
                'HTTP_COOKIE' => 'PHPSESSID=app-session',
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('app-user:app-password'),
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ],
        );

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $headers = $this->forwardedHeaders();
        self::assertSame(['accept: application/json'], $headers['accept'] ?? null);
        self::assertSame(['x-requested-with: XMLHttpRequest'], $headers['x-requested-with'] ?? null);
        foreach (['cookie', 'authorization', 'php-auth-user', 'php-auth-pw'] as $name) {
            self::assertArrayNotHasKey($name, $headers, $name . ' must not reach the target');
        }
    }

    public function testDoesNotPassTheTargetsCookiesBackToTheBrowser(): void
    {
        $this->upstream->enqueue(new MockResponse('IMAGE-BYTES', [
            'http_code' => 200,
            'response_headers' => [
                'content-type' => 'image/png',
                'set-cookie' => 'EMSESSION=target-session; path=/',
                'x-upstream' => 'kept',
            ],
        ]));

        $this->client->request('GET', '/assetpicker?to=' . urlencode('https://em.example.org/app/a.png'));

        $response = $this->client->getResponse();
        self::assertSame('image/png', $response->headers->get('content-type'));
        self::assertSame('kept', $response->headers->get('x-upstream'));
        self::assertNull($response->headers->get('set-cookie'));
        self::assertSame([], $response->headers->getCookies());
        self::assertSame([], $this->client->getCookieJar()->all());
    }

    /**
     * The headers of the single forwarded request, keyed by lower-case name.
     *
     * @return array<string, list<string>>
     */
    private function forwardedHeaders(): array
    {
        self::assertCount(1, $this->upstream->requests);
        $headers = $this->upstream->requests[0]['options']['normalized_headers'] ?? null;
        self::assertIsArray($headers);

        /** @var array<string, list<string>> $headers */
        return $headers;
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function proxyRouteUrls(): iterable
    {
        yield 'rewriting front controller' => ['/assetpicker'];
        yield 'front controller in the URL' => ['/index.php/assetpicker'];
    }

    #[DataProvider('proxyRouteUrls')]
    public function testRewritesAnUpstreamRedirectBackThroughTheProxyRoute(string $proxyPath): void
    {
        $location = 'https://cdn.example.org/final.png';
        $this->upstream->enqueue(new MockResponse('', [
            'http_code' => 302,
            'response_headers' => ['location' => $location],
        ]));

        $this->client->request(
            'GET',
            'https://app.example.test' . $proxyPath . '?to=' . urlencode('https://em.example.org/app/a.png'),
            server: ['SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => '/srv/app/public/index.php'],
        );

        $response = $this->client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        self::assertSame(
            'https://app.example.test' . $proxyPath . '?to=' . urlencode($location),
            $response->headers->get('location'),
        );
        self::assertCount(1, $this->upstream->requests, 'the redirect must not be followed');
    }

    public function testRejectsARequestWithoutTarget(): void
    {
        $this->client->request('GET', '/assetpicker');

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame([], $this->upstream->requests);
    }

    public function testRejectsAnArrayTarget(): void
    {
        $this->client->request('GET', '/assetpicker?to[]=https://em.example.org/');

        self::assertSame(400, $this->client->getResponse()->getStatusCode());
        self::assertSame([], $this->upstream->requests);
    }
}
