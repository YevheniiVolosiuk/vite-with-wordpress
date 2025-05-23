# Vite With WordPress
[![Latest Version on Packagist](https://img.shields.io/packagist/v/stubbornweb/vite-with-wordpress.svg?style=flat-square)](https://packagist.org/packages/stubbornweb/vite-with-wordpress)
[![Tests](https://img.shields.io/github/actions/workflow/status/yevheniivolosiuk/vite-with-wordpress/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/yevheniivolosiuk/vite-with-wordpress/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/yevheniivolosiuk/vite-with-wordpress.svg?style=flat-square)](https://packagist.org/packages/yevheniivolosiuk/vite-with-wordpress)

A simple, elegant Vite integration for WordPress themes and plugins — inspired by Laravel's Vite helper, providing seamless asset management, HMR support, and production-ready manifest handling.
---
Seamlessly integrate [Vite](https://vitejs.dev/) into your WordPress theme or plugin for fast, modern frontend development with hot module replacement (HMR) and efficient production builds.

## Why use this?

- Native WordPress support for Vite assets
- Automatic dev server detection with HMR support
- Manifest-based asset URL resolution for production
- Easy static facade: `Vite::asset()` to get asset URLs
- Injects `type="module"` on scripts for ES modules support
- Supports extracting and enqueuing CSS linked from JS entrypoints

---
## Required Directory Structure and Naming
For the integration to work correctly, **your plugin or theme must have the following directories with exact naming** at the root level:
```php
root/
├── resources/
├── public/
```
- `resources/` — Place your source assets here, e.g. `resources/js/main.js`, `resources/scss/style.scss`, etc.
- `public/` — This is the build output directory where Vite will place compiled assets, including the `build/` folder and `manifest.json`.

### Important

- These directories **must exist** and follow the naming convention shown above by default.
- If you want to use different directory names or locations, **you must update your `vite.config.js` accordingly** to reflect your custom paths.
- For example, change the `base` option and any path aliases in your Vite config to match your folder names.

---

## Installation

1. **Clone or install the package into your WordPress theme or plugin folder via composer or copy just 2 classes located in `src/` to your project**

```bash
    composer require stubbornweb/vite-with-wordpress
```

2. **Set up your Vite project** with your assets inside the theme/plugin directory, e.g. `resources/js/main.js`

3. **Ensure your Vite config outputs assets to `/public/build`** inside your theme/plugin directory

4. **Include PHP classes and `autoload` or simply `require` them into your theme/plugin:

```php
// Bootstrap plugin/theme file

use StubbornWeb\ViteWithWordPress\Vite;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
```

---
Bootstrap without composer via native PHP
```php
// Bootstrap plugin/theme file

require_once 'path_to_new_classes/Vite.php'
```

## Vite Configuration Example

```js
// vite.config.js

import path from 'path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    base: '/wp-content/themes/vite-with-wordpress/public/build',
    plugins: [
        laravel({
            input: [
                'resources/js/main.js',
                'resources/js/plugins/slider.js',
            ],
            refresh: [
                {
                    paths: ['/**/*.php', '*.php']
                }
            ],
        }),
    ],
    resolve: {
        alias: {
            '@styles': path.resolve(__dirname, 'resources/scss'),
            '@scripts': path.resolve(__dirname, 'resources/js'),
            '@images': path.resolve(__dirname, 'resources/images'),
            '@fonts': path.resolve(__dirname, 'resources/fonts'),
        },
    },
    server: {
        host: "0.0.0.0",
        port: 5173,
        strictPort: true,
        cors: {
            origin: "https://your-local-dev-url.test",
            credentials: true,
        },
        hmr: {
            host: "localhost",
            protocol: "ws",
        },
    },
});
```

## Usage in WordPress

```php
use StubbornWeb\ViteWithWordPress\Vite;

add_action('wp_enqueue_scripts', function () {
    // ✅ Recommended: Include "vite" in handle to enable `type="module"`
    wp_enqueue_script(
        'vite-main-script-file',
        Vite::asset('resources/js/main.js'),
        ['jquery'],
        '1.0.0',
        true
    );

    wp_enqueue_style(
        'vite-main-style-file',
        Vite::asset('resources/js/main.js', 'css'),
        [],
        '1.0.0',
    );

    // 🚫 Not recommended if you want `type="module"` automatically
    // This script will not get the type="module" attribute
    wp_enqueue_script(
        'main-script-file',
        Vite::asset('resources/js/main.js'),
        ['jquery'],
        '1.0.0',
        true
    );
});
```
---
# How It Works

### Development mode (npm run dev):
- Detects Vite dev server by checking hot file presence and uses HMR URLs for assets.

### Production mode (npm run build):
- Loads manifest.json from public/build/ to resolve hashed filenames for cache-busting.

### Script tag enhancement (type="module"):
- By default, Vite assets loaded via wp_enqueue_script() will automatically get type="module" injected to support ES modules.
- ✅ This behavior only applies if the script handle contains the keyword vite (e.g. vite-main-script-file).
- 🚫 If you use a generic or unrelated handle like main-script-file, this enhancement will be skipped to avoid affecting non-Vite scripts.
---

# API

```php
Vite::asset(string $assetPath, string|bool $css = ''): ?string
```
- Returns the full URL of the asset, adapting automatically to dev server or production build.
- The second argument ('css' or true) returns the CSS file linked from a JS entrypoint, if available.
- Returns null if the asset is unavailable.

---

# Troubleshooting
- Verify `manifest.json` exists in public/build after running build.
- Confirm `hot` file is present during dev server run.
- Make sure your WordPress URL and Vite config base & server.cors.origin are correctly set.
- Errors related to missing assets will trigger a detailed WordPress error message including suggestions.

# Contributing

Contributions welcome! Open an issue or pull request to improve the integration.

# About

Created and maintained by Yevhenii Volosiuk for modern WordPress development with Vite.

Inspired by Laravel.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
