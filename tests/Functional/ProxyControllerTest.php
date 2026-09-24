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
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(ProxyController::class)]
final class ProxyControllerTest extends WebTestCase
{
    /**
     * A public address as the target host. The proxy's client checks the
     * address before the (mocked) request, and a host name would need DNS;
     * the documentation ranges (192.0.2.0/24 and so on) are in
     * IpUtils::PRIVATE_SUBNETS and would be refused.
     */
    private const string PUBLIC_HOST = 'https://93.184.215.14';

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

        $this->client->request('GET', '/assetpicker?to=' . urlencode(self::PUBLIC_HOST . '/app/a.png?size=large'));

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('IMAGE-BYTES', $this->client->getInternalResponse()->getContent());
        self::assertSame('image/png', $response->headers->get('content-type'));

        self::assertCount(1, $this->upstream->requests);
        self::assertSame('GET', $this->upstream->requests[0]['method']);
        self::assertSame(self::PUBLIC_HOST . '/app/a.png?size=large', $this->upstream->requests[0]['url']);
    }

    public function testForwardsMethodAndBody(): void
    {
        $this->upstream->enqueue(new MockResponse('{"ok":true}', ['http_code' => 201]));

        $this->client->request(
            'POST',
            '/assetpicker?to=' . urlencode(self::PUBLIC_HOST . '/app/login'),
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
            '/assetpicker?to=' . urlencode(self::PUBLIC_HOST . '/app/a.json'),
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

        $this->client->request('GET', '/assetpicker?to=' . urlencode(self::PUBLIC_HOST . '/app/a.png'));

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
            'https://app.example.test' . $proxyPath . '?to=' . urlencode(self::PUBLIC_HOST . '/app/a.png'),
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
    /**
     * @return iterable<string, array{string}>
     */
    public static function privateTargets(): iterable
    {
        yield 'loopback' => ['http://127.0.0.1/admin'];
        yield 'loopback IPv6' => ['http://[::1]/admin'];
        yield 'localhost' => ['http://localhost/admin'];
        yield 'RFC 1918 10/8' => ['http://10.0.0.1/'];
        yield 'RFC 1918 172.16/12' => ['http://172.16.0.1/'];
        yield 'RFC 1918 192.168/16' => ['http://192.168.1.1/'];
        yield 'link-local, cloud metadata' => ['http://169.254.169.254/latest/meta-data/'];
        yield 'link-local IPv6' => ['http://[fe80::1]/'];
        yield 'unique local IPv6' => ['http://[fd00::1]/'];
        // .invalid never resolves (RFC 6761); an address that cannot be
        // checked is refused.
        yield 'unresolvable host' => ['http://assetpicker.invalid/'];
    }

    #[DataProvider('privateTargets')]
    public function testRefusesAPrivateTargetWithoutRequestingIt(string $target): void
    {
        $this->client->request('GET', '/assetpicker?to=' . urlencode($target));

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame([], $this->upstream->requests, 'the target must not be requested');
    }

    public function testRefusesATargetWhoseConnectionEndsUpOnAPrivateAddress(): void
    {
        // The address the client connected to (primary_ip) is checked too.
        $this->upstream->enqueue(new MockResponse('INTERNAL', ['http_code' => 200, 'primary_ip' => '10.0.0.5']));

        $this->client->request('GET', '/assetpicker?to=' . urlencode(self::PUBLIC_HOST . '/app/a.png'));

        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testRefusesARedirectFromAPublicTargetToAPrivateAddress(): void
    {
        $private = 'http://169.254.169.254/latest/meta-data/';
        $this->upstream->enqueue(new MockResponse('', [
            'http_code' => 302,
            'response_headers' => ['location' => $private],
        ]));

        // The redirect is not followed but sent back to the browser, pointing
        // at the proxy route again ...
        $this->client->request('GET', 'https://app.example.test/assetpicker?to=' . urlencode(self::PUBLIC_HOST . '/app/a.png'));
        $location = $this->client->getResponse()->headers->get('location');
        self::assertSame('https://app.example.test/assetpicker?to=' . urlencode($private), $location);

        // ... where the redirect target is checked like any other target.
        $this->client->request('GET', $location);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertCount(1, $this->upstream->requests, 'the private redirect target must not be requested');
    }

    public function testATransportErrorThatIsNotARefusalIsNotReportedAsForbidden(): void
    {
        $this->upstream->enqueue(new MockResponse([new \RuntimeException('Connection timed out')]));

        $this->client->request('GET', '/assetpicker?to=' . urlencode(self::PUBLIC_HOST . '/app/a.png'));

        self::assertSame(500, $this->client->getResponse()->getStatusCode());
    }

    public function testTheApplicationsHttpClientStillReachesPrivateAddresses(): void
    {
        $this->upstream->enqueue(new MockResponse('INTERNAL', ['http_code' => 200]));
        $httpClient = self::getContainer()->get('http_client');
        self::assertInstanceOf(HttpClientInterface::class, $httpClient);

        $response = $httpClient->request('GET', 'http://10.0.0.1/internal');

        self::assertSame('INTERNAL', $response->getContent());
        self::assertSame('http://10.0.0.1/internal', $this->upstream->requests[0]['url']);
    }
}
