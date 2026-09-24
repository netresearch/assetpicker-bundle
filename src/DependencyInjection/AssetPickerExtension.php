<?php

/**
 * See class comment
 *
 * @category Netresearch
 * @package  Netresearch\AssetPickerBundle\DependencyInjection
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/netresearch/assetpicker-bundle
 */

declare(strict_types=1);

namespace Netresearch\AssetPickerBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Loads the bundle services and exposes the `asset_picker` configuration as
 * the `assetpicker` container parameter.
 *
 * The configuration is the AssetPicker JavaScript configuration and is passed
 * to the browser as is, so it is not validated here: every configuration file
 * that sets `asset_picker` is merged recursively, later files winning.
 */
final class AssetPickerExtension extends Extension
{
    /**
     * @param array<array<mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config'));
        $loader->load('services.php');

        $container->setParameter('assetpicker', array_replace_recursive([], ...$configs));
    }

    public function getAlias(): string
    {
        return 'asset_picker';
    }
}
