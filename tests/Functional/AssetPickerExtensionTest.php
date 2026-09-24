<?php

declare(strict_types=1);

namespace Netresearch\AssetPickerBundle\Tests\Functional;

use Netresearch\AssetPickerBundle\DependencyInjection\AssetPickerExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(AssetPickerExtension::class)]
final class AssetPickerExtensionTest extends TestCase
{
    public function testAShorterListReplacesTheEarlierList(): void
    {
        self::assertSame(
            ['pick' => ['types' => ['file'], 'limit' => 1]],
            $this->load(
                ['pick' => ['types' => ['file', 'dir'], 'limit' => 1]],
                ['pick' => ['types' => ['file']]],
            ),
        );
    }

    public function testAnEmptyListReplacesTheEarlierList(): void
    {
        self::assertSame(
            ['pick' => ['types' => [], 'extensions' => ['png']]],
            $this->load(
                ['pick' => ['types' => ['file', 'dir'], 'extensions' => ['png']]],
                ['pick' => ['types' => []]],
            ),
        );
    }

    public function testAnEmptyValueReplacesAnEarlierMap(): void
    {
        self::assertSame(
            ['pick' => [], 'thumbnails' => 'url'],
            $this->load(
                ['pick' => ['limit' => 1], 'thumbnails' => 'url'],
                ['pick' => []],
            ),
        );
    }

    /**
     * @param array<mixed> ...$configs
     *
     * @return mixed the `assetpicker` parameter
     */
    private function load(array ...$configs): mixed
    {
        $container = new ContainerBuilder();
        (new AssetPickerExtension())->load(array_values($configs), $container);

        return $container->getParameter('assetpicker');
    }
}
