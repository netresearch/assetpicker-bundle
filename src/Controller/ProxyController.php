<?php

/**
 * See class comment
 *
 * @category Netresearch
 * @package  Netresearch\AssetPickerBundle\Controller
 * @author   Christian Opitz <christian.opitz@netresearch.de>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/netresearch/assetpicker-bundle
 */

declare(strict_types=1);

namespace Netresearch\AssetPickerBundle\Controller;

use Netresearch\AssetPicker\Proxy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Forwards the request to the URL given in the `to` query parameter and
 * returns the upstream response, for storages that send no CORS headers.
 */
final class ProxyController
{
    public function __construct(
        private readonly Proxy $proxy,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $target = $request->query->getString('to');
        if ($target === '') {
            throw new BadRequestHttpException('No target provided');
        }

        return $this->proxy->forward($request, $target);
    }
}
