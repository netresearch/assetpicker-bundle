<?php

declare(strict_types=1);

namespace Netresearch\AssetPickerBundle\Tests\Functional;

use Netresearch\AssetPicker\Proxy;
use Netresearch\AssetPickerBundle\AssetPickerBundle;
use Netresearch\AssetPickerBundle\Controller\ProxyController;
use Netresearch\AssetPickerBundle\DependencyInjection\AssetPickerExtension;
use Netresearch\AssetPickerBundle\Twig\AssetPickerExtension as TwigExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

#[CoversClass(AssetPickerBundle::class)]
#[CoversClass(AssetPickerExtension::class)]
final class KernelBootTest extends KernelTestCase
{
    public function testMergesAllAssetPickerConfigurationsRecursively(): void
    {
        self::bootKernel();

        self::assertSame(
            [
                'storages' => [
                    'media' => ['adapter' => 'entermediadb', 'url' => 'https://em.example.org/app', 'proxy' => true],
                    'repo' => ['adapter' => 'github', 'username' => 'netresearch', 'repository' => 'assetpicker'],
                ],
                'pick' => ['limit' => 3],
            ],
            self::getContainer()->getParameter('assetpicker'),
        );
    }

    public function testRegistersTheServices(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertInstanceOf(Proxy::class, $container->get('assetpicker.proxy'));
        self::assertInstanceOf(ProxyController::class, $container->get(ProxyController::class));
        $twig = $container->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        self::assertTrue($twig->hasExtension(TwigExtension::class));
    }

    public function testImportsTheProxyRoute(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        $route = $router->getRouteCollection()->get('assetpicker_proxy');
        self::assertNotNull($route);
        self::assertSame('/assetpicker', $route->getPath());
        self::assertSame(ProxyController::class, $route->getDefault('_controller'));
    }
}
