<?php

declare(strict_types=1);

namespace Netresearch\AssetPickerBundle\Tests\Fixtures;

use Netresearch\AssetPickerBundle\AssetPickerBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal application with the bundle enabled and its routes imported.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new AssetPickerBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/assetpicker-bundle-tests/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/assetpicker-bundle-tests/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_client' => [
                'mock_response_factory' => RecordingMockResponseFactory::class,
            ],
        ]);

        // Two files setting asset_picker: they are merged recursively.
        $container->extension('asset_picker', [
            'storages' => [
                'media' => ['adapter' => 'entermediadb', 'url' => 'https://em.example.org/app', 'proxy' => true],
            ],
            'pick' => ['limit' => 1],
        ]);
        $container->extension('asset_picker', [
            'storages' => [
                'repo' => ['adapter' => 'github', 'username' => 'netresearch', 'repository' => 'assetpicker'],
            ],
            'pick' => ['limit' => 3],
        ]);

        $services = $container->services();
        $services->set(RecordingMockResponseFactory::class)
            ->public();
        // Instead of the default logger, which writes to stderr.
        $services->set('logger', NullLogger::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@AssetPickerBundle/config/routes.php');
    }
}
