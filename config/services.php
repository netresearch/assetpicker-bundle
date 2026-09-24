<?php

declare(strict_types=1);

use Netresearch\AssetPicker\Proxy;
use Netresearch\AssetPickerBundle\Controller\ProxyController;
use Netresearch\AssetPickerBundle\Twig\AssetPickerExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The application's http_client, so its configuration (timeouts, a
    // decorating client, mock responses in tests) applies to proxied requests.
    $services->set('assetpicker.proxy', Proxy::class)
        ->args([service('http_client')]);

    $services->set(ProxyController::class)
        ->args([service('assetpicker.proxy')])
        ->tag('controller.service_arguments');

    $services->set('assetpicker.twig_extension', AssetPickerExtension::class)
        ->args([param('assetpicker'), service('router')])
        ->tag('twig.extension');
};
