<?php

declare(strict_types=1);

namespace Netresearch\AssetPickerBundle\Tests\Functional;

use Netresearch\AssetPickerBundle\Twig\AssetPickerExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Twig\Environment;

#[CoversClass(AssetPickerExtension::class)]
final class TwigExtensionTest extends KernelTestCase
{
    public function testRendersTheConfigurationWithTheProxyRouteAsProxyUrl(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        $json = $twig->createTemplate('{{ assetpicker_config() }}')->render([]);

        self::assertSame(
            [
                'storages' => [
                    'media' => ['adapter' => 'entermediadb', 'url' => 'https://em.example.org/app', 'proxy' => true],
                    'repo' => ['adapter' => 'github', 'username' => 'netresearch', 'repository' => 'assetpicker'],
                ],
                'pick' => ['limit' => 3],
                'proxy' => ['url' => '/assetpicker?to={{url}}'],
            ],
            json_decode($json, true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function testKeepsAConfiguredProxyUrl(): void
    {
        $extension = new AssetPickerExtension(
            ['proxy' => ['url' => 'https://proxy.example.org/?u={{url}}', 'all' => true]],
            $this->generatorWithProxyRoute(),
        );

        self::assertSame(
            '{"proxy":{"url":"https://proxy.example.org/?u={{url}}","all":true}}',
            $extension->renderConfig(),
        );
    }

    public function testAddsTheProxyUrlNextToOtherProxyOptions(): void
    {
        $extension = new AssetPickerExtension(['proxy' => ['all' => true]], $this->generatorWithProxyRoute());

        self::assertSame('{"proxy":{"all":true,"url":"/assetpicker?to={{url}}"}}', $extension->renderConfig());
    }

    public function testRendersAnEmptyObjectWithoutConfigurationAndProxyRoute(): void
    {
        $extension = new AssetPickerExtension([], new UrlGenerator(new RouteCollection(), new RequestContext()));

        self::assertSame('{}', $extension->renderConfig());
    }

    public function testOutputCannotCloseTheSurroundingScriptElement(): void
    {
        $extension = new AssetPickerExtension(
            ['title' => '</script><script>alert(1)</script> & more'],
            new UrlGenerator(new RouteCollection(), new RequestContext()),
        );

        $json = $extension->renderConfig();

        self::assertStringNotContainsString('<', $json);
        self::assertStringNotContainsString('>', $json);
        self::assertSame(
            ['title' => '</script><script>alert(1)</script> & more'],
            json_decode($json, true, flags: JSON_THROW_ON_ERROR),
        );
    }

    private function generatorWithProxyRoute(): UrlGeneratorInterface
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(UrlGeneratorInterface::class, $router);

        return $router;
    }
}
