<?php

/**
 * See class comment
 *
 * @category Netresearch
 * @package  Netresearch\AssetPickerBundle
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/netresearch/assetpicker-bundle
 */

declare(strict_types=1);

namespace Netresearch\AssetPickerBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Integrates AssetPicker into a Symfony application: the proxy route and the
 * assetpicker_config() Twig function.
 */
final class AssetPickerBundle extends Bundle
{
    /**
     * The bundle root, so @AssetPickerBundle/config/... resolves to config/.
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
