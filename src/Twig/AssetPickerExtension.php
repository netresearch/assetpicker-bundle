<?php

/**
 * See class comment
 *
 * @category Netresearch
 * @package  Netresearch\AssetPickerBundle\Twig
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/netresearch/assetpicker-bundle
 */

declare(strict_types=1);

namespace Netresearch\AssetPickerBundle\Twig;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides the assetpicker_config() Twig function.
 */
final class AssetPickerExtension extends AbstractExtension
{
    /**
     * @param array<mixed> $config The merged `asset_picker` configuration
     */
    public function __construct(
        private readonly array $config,
        private readonly UrlGeneratorInterface $generator,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('assetpicker_config', $this->renderConfig(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * The configuration as JSON. When the proxy route is imported and no
     * proxy.url is configured, proxy.url points at that route.
     *
     * `<`, `>` and `&` are escaped as <, > and &, so the output
     * cannot close the surrounding `<script>` element.
     */
    public function renderConfig(): string
    {
        $config = $this->config;
        if (!isset($config['proxy']) || !\is_array($config['proxy']) || !isset($config['proxy']['url'])) {
            try {
                $proxy = \is_array($config['proxy'] ?? null) ? $config['proxy'] : [];
                $proxy['url'] = $this->generator->generate('assetpicker_proxy') . '?to={{url}}';
                $config['proxy'] = $proxy;
            } catch (RouteNotFoundException) {
                // The proxy route is optional.
            }
        }

        if ($config === []) {
            return '{}';
        }

        return json_encode(
            $config,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR,
        );
    }
}
