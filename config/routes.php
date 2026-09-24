<?php

declare(strict_types=1);

use Netresearch\AssetPickerBundle\Controller\ProxyController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('assetpicker_proxy', '/assetpicker')
        ->controller(ProxyController::class);
};
