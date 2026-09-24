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
 * that sets `asset_picker` is merged, later files winning. Maps are merged
 * recursively; a list (such as `pick.types`) or an empty value replaces the
 * earlier value as a whole.
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

        $container->setParameter('assetpicker', array_reduce($configs, self::merge(...), []));
    }

    /**
     * Merge $override onto $base: keys present in both are merged recursively
     * when both values are non-empty maps (`array_is_list()` is true for
     * an empty array), otherwise the override wins.
     *
     * @param array<mixed> $base
     * @param array<mixed> $override
     *
     * @return array<mixed>
     */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $current = $base[$key] ?? null;
            $base[$key] = \is_array($value) && \is_array($current) && !array_is_list($value) && !array_is_list($current)
                ? self::merge($current, $value)
                : $value;
        }

        return $base;
    }

    public function getAlias(): string
    {
        return 'asset_picker';
    }
}
