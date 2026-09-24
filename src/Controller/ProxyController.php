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
 *
 * The route runs on the application's domain, so the browser sends the
 * application's cookies and HTTP authentication with every request to it.
 * Those credentials are not forwarded to the target, and cookies the target
 * sets are not passed back to the browser, where they would be stored for the
 * application's domain.
 */
final class ProxyController
{
    /**
     * Request headers carrying the application's credentials. PHP_AUTH_USER,
     * PHP_AUTH_PW and PHP_AUTH_DIGEST appear as headers because
     * {@see \Symfony\Component\HttpFoundation\ServerBag::getHeaders()} derives
     * them from HTTP authentication.
     */
    private const array CREDENTIAL_HEADERS = [
        'cookie',
        'authorization',
        'php-auth-user',
        'php-auth-pw',
        'php-auth-digest',
    ];

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

        $forwarded = clone $request;
        foreach (self::CREDENTIAL_HEADERS as $header) {
            $forwarded->headers->remove($header);
        }

        $response = $this->proxy->forward($forwarded, $target);
        $response->headers->remove('set-cookie');

        return $response;
    }
}
