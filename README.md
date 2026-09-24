# AssetPicker Symfony Bundle

This Symfony bundle integrates [AssetPicker](https://github.com/netresearch/assetpicker) into Symfony applications: it serves the AssetPicker proxy as a route and renders the picker configuration from your Symfony configuration with a Twig function.

Requirements: PHP 8.4 or later, Symfony 8, `netresearch/assetpicker` 2.x.

## Installation

1. Install via Composer:

    ```bash
    composer require netresearch/assetpicker-bundle
    ```

2. Enable the bundle in `config/bundles.php` (Symfony Flex does this for you):

    ```php
    return [
        // ...
        Netresearch\AssetPickerBundle\AssetPickerBundle::class => ['all' => true],
    ];
    ```

3. Add the [AssetPicker configuration](https://github.com/netresearch/assetpicker#configuration) in `config/packages/asset_picker.yaml`. It is passed to the picker as is:

    ```yaml
    asset_picker:
        storages:
            media:
                adapter: entermediadb
                url: "https://em.example.org/openinstitute"
                proxy: true
            repo:
                adapter: github
                username: "netresearch"
                repository: "assetpicker"
        pick:
            limit: 1
    ```

    When several configuration files set `asset_picker` (for example one per environment), they are merged recursively.

4. (Optional) To use the proxy, import its route in `config/routes/asset_picker.yaml`. `assetpicker_config()` then sets `proxy.url` to the route automatically, unless you configure `proxy.url` yourself:

    ```yaml
    assetpicker_proxy:
        resource: "@AssetPickerBundle/config/routes.php"
    ```

    The route is `/assetpicker?to=<url>`. Use a `prefix` on the import to move it.

## Usage

### The picker JavaScript

AssetPicker 2 is a Vue 3 application that you build into your own frontend. It is not published on npm (the `assetpicker` package on npm is the old 1.3.4), so install it from the Git tag and build it with a bundler that compiles Vue single-file components, for example Vite with `@vitejs/plugin-vue` or Webpack Encore with `enableVueLoader()`:

```bash
npm install github:netresearch/assetpicker#2.0.0
npm install --save-dev vite @vitejs/plugin-vue
```

A minimal entry point that opens the picker from a button and hands the picked asset to your code:

```js
// assets/assetpicker.js
import { createAssetPickerApp } from 'assetpicker';

const config = JSON.parse(document.getElementById('assetpicker-config').textContent);

document.querySelectorAll('[data-assetpicker]').forEach((button) => {
  button.addEventListener('click', () => {
    const picker = createAssetPickerApp({
      el: button.dataset.assetpicker,
      config,
      onFinish(result, cancelled) {
        picker.unmount();
        if (!cancelled) {
          button.dispatchEvent(new CustomEvent('assetpicker:pick', { detail: result }));
        }
      },
    });
  });
});
```

See the [AssetPicker README](https://github.com/netresearch/assetpicker#usage) for `createAssetPickerApp`, the result format and custom adapters.

### The configuration

The Twig function `assetpicker_config()` returns the `asset_picker` configuration as JSON, with `proxy.url` pointing at the proxy route when the route is imported. Render it into a JSON script element and read it from your entry point:

```twig
<script type="application/json" id="assetpicker-config">{{ assetpicker_config() }}</script>

<button type="button" data-assetpicker="#assetpicker-mount">Pick an asset</button>
<div id="assetpicker-mount"></div>
```

`<`, `>` and `&` in configuration values are escaped, so a value cannot end the script element.

### The proxy

Storages that send no CORS headers, such as EnterMediaDB, need the proxy. The route forwards the request to the URL in its `to` parameter with the application's `http_client` service and returns the upstream response. Redirects are not followed; their `Location` is rewritten to go through the route again. A request without `to` is answered with `400 Bad Request`.

The route runs on your application's domain, so the browser sends your application's cookies and HTTP authentication along. The proxy does not forward them: `Cookie` and `Authorization` are removed from the forwarded request, and `Set-Cookie` from the upstream response. A storage that needs a session cookie or an `Authorization` header therefore cannot be used through the proxy.

The proxy forwards to any URL it is given. Restrict access to the route with your firewall and `access_control`, and configure the `http_client` service accordingly, for example with timeouts or a decorating `Symfony\Component\HttpClient\NoPrivateNetworkHttpClient` when the proxy must not reach internal hosts.

## Upgrading from 1.x

Version 2 targets AssetPicker 2, which replaced the script-tag picker with a Vue 3 application and no longer ships a built `picker.js`.

- PHP 8.4 and Symfony 8 are required.
- The `assetpicker_url()` Twig function and the `assets:install` listener that copied `picker.js` into `public/bundles/assetpicker/` are removed. Build the picker as described above, and replace `new AssetPicker({{ assetpicker_config() }})` and `rel="assetpicker"` buttons with `createAssetPickerApp()`.
- The route resource moved from `@AssetPickerBundle/Resources/config/routing.yml` to `@AssetPickerBundle/config/routes.php`. Route name (`assetpicker_proxy`) and path (`/assetpicker`) are unchanged.
- The proxy answers a request without `to` with `400` instead of an uncaught exception (`500`).
- AssetPicker 2 renamed configuration keys: `picker.*` is now `pick.*`; `adapters` and `debug` are removed. See the [AssetPicker changelog](https://github.com/netresearch/assetpicker/blob/main/CHANGELOG.md).

## Development

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

## License

MIT
