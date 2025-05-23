<?php

declare(strict_types=1);

namespace StubbornWeb\ViteWithWordPress\ViteBase;

/**
 * ViteBase class for WordPress Vite integration.
 *
 * Handles asset management, manifest loading, and script tag modifications
 * to support Vite's development and production modes within WordPress themes/plugins.
 *
 * Provides a singleton instance and utility methods to resolve asset URLs,
 * Detect running dev server, and inject `type="module"` attribute for ES modules.
 */
class ViteBase
{
    /*
     * Singleton Vite::class instance
     */
    protected static ?self $instance = null;

    /**
     * Tracks if the script_loader_tag hook is already added.
     */
    protected bool $scriptHooked = false;

    /**
     * The configured entry points.
     */
    protected array $entryPoints = [];

    /**
     * The path to the "hot" file.
     */
    protected ?string $hotFile;

    /**
     * The path to the build directory.
     */
    protected string $buildDirectory = 'build';

    /**
     * The name of the manifest file.
     */
    protected string $manifestFilename = 'manifest.json';

    /**
     * The custom asset path resolver.
     *
     * @var callable|null
     */
    protected $assetPathResolver = null;

    /**
     * The cached manifest files.
     */
    protected static array $manifests = [];

    /**
     * Constructor.
     *
     * @param  array  $config  Configuration options like 'buildDirectory', 'hotFile', 'assetPathResolver'.
     */
    public function __construct(array $config = [])
    {
        $this->buildDirectory = $config['buildDirectory'] ?? $this->buildDirectory;
        $this->hotFile = $config['hotFile'] ?? null;
        $this->assetPathResolver = $config['assetPathResolver'] ?? null;

        // Default hotFile path if not provided
        if ($this->hotFile === null) {
            $this->hotFile = $this->hotFile();
        }

        if (! $this->scriptHooked) {
            add_filter('script_loader_tag', [$this, 'addModuleAttributeForViteAssets'], 10, 3);
            $this->scriptHooked = true;
        }
    }

    /**
     * Return or set the singleton instance.
     */
    public static function getInstance(): self
    {

        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Create or return the singleton instance with configuration.
     */
    public static function make(array $config = []): static
    {
        if (static::$instance === null) {
            static::$instance = new static($config);
        }

        return static::$instance;
    }

    /**
     * Resolve the URL for a given asset based on manifest and environment mode.
     */
    public function asset(
        string $asset,
        string|bool $cssArg = '',
    ): ?string {
        $type = is_string($cssArg) || is_bool($cssArg) ? $cssArg : null;
        $cssEntry = $type === 'css' || $type === true;
        $buildDirectory = $args['buildDirectory'] ?? $this->buildDirectory;

        if ($this->isRunningHot()) {
            if ($cssEntry) {
                return null;
            }

            return $this->hotAsset($asset);
        }

        $manifest = $this->manifest($buildDirectory);
        $chunk = $this->getChunkOrFail($manifest, $asset);
        $ext = pathinfo($asset, PATHINFO_EXTENSION);

        return match ($ext) {
            'js' => $this->handleJs($chunk, $buildDirectory, $cssEntry),
            default => $this->handleAsset($chunk, $buildDirectory),
        };
    }

    /**
     * Filter callback to add `type="module"` attribute to Vite JS script tags.
     */
    public function addModuleAttributeForViteAssets(string $tag, string $handle, string $src): string
    {
        if ($this->isViteAsset($handle) && $this->isJsFile($src)) {
            // Remove any existing type="text/javascript"
            $tag = preg_replace('/\s+type=["\']text\/javascript["\']/', '', $tag);

            // Inject type="module" into the script tag
            $tag = preg_replace('/<script(\s+)/', '<script type="module"$1', $tag);
            error_log(print_r($tag, true));
        }

        return $tag;
    }

    /**
     * Get the manifest file for the given build directory.
     */
    public function manifest(string $buildDirectory): array
    {
        $path = $this->manifestPath($buildDirectory);

        if (! isset(static::$manifests[$path])) {
            if (! is_file($path)) {
                wp_die("Vite manifest not found at: $path");
            }

            static::$manifests[$path] = json_decode(file_get_contents($path), true);
        }

        return static::$manifests[$path];
    }

    /**
     * Get a unique hash representing the current manifest, or null if there is no manifest.
     */
    public function manifestHash(?string $buildDirectory = null): ?string
    {
        $buildDirectory ??= $this->buildDirectory;

        if ($this->isRunningHot()) {
            return null;
        }

        if (! is_file($path = $this->manifestPath($buildDirectory))) {
            return null;
        }

        return md5_file($path) ?: null;
    }

    /**
     * Determine if the HMR server is running.
     */
    public function isRunningHot(): bool
    {
        return is_file($this->hotFile());
    }

    /**
     * Get the path to the manifest file for the given build directory.
     */
    protected function manifestPath(string $buildDirectory): string
    {
        return $this->publicPathDir($buildDirectory.'/'.$this->manifestFilename);
    }

    /**
     * Get the Vite "hot" file path.
     */
    protected function hotFile(): string
    {
        return $this->hotFile ?? $this->publicPathDir('/hot');
    }

    /**
     * Get the public file path.
     */
    protected function publicPath(string $path): string
    {
        return get_template_directory_uri().'/public/'.ltrim($path, '/');
    }

    /**
     * Get the public server dir file path.
     */
    protected function publicPathDir(string $path): string
    {
        return get_template_directory().'/public/'.ltrim($path, '/');
    }

    /**
     * Get the chunk for the given entry point / asset.
     */
    protected function getChunkOrFail(array $manifest, string $file): array
    {
        if (! isset($manifest[$file])) {
            $keys = array_keys($manifest);

            // Suggest the most similar match
            $suggested = '';
            $maxSimilarity = 0;
            foreach ($keys as $key) {
                similar_text($file, $key, $percent);
                if ($percent > $maxSimilarity) {
                    $maxSimilarity = $percent;
                    $suggested = $key;
                }
            }

            $message = "❌ Unable to locate asset in Vite manifest: '{$file}'.";

            if ($suggested && $maxSimilarity > 60) {
                $message .= "\n\n👉 Did you mean: '{$suggested}'?";
            }

            $message .= "\n\n📦 Available manifest keys:\n - ".implode("\n - ", $keys);

            wp_die(nl2br(esc_html($message)));
        }

        return $manifest[$file];
    }

    /**
     * Determine whether the given path is a CSS file.
     */
    protected function isCssPath(string $path): bool
    {
        return preg_match('/\.(css|less|sass|scss|styl|stylus|pcss|postcss)(\?[^.]*)?$/', $path) === 1;
    }

    /**
     * Get the path to a given asset when running in HMR mode.
     */
    protected function hotAsset($asset): string
    {
        return rtrim(file_get_contents($this->hotFile())).'/'.$asset;
    }

    /**
     * Generate an asset path for the application.
     */
    protected function assetPath(string $path, ?bool $secure = null): string
    {
        if ($this->assetPathResolver !== null) {
            return call_user_func($this->assetPathResolver, $path, $secure);
        }

        return $this->publicPath($path);
    }

    /**
     * Handle JavaScript chunk assets from the manifest.
     */
    protected function handleJs(array $chunk, string $dir, bool $cssEntry = false): ?string
    {
        if ($cssEntry && ! empty($chunk['css'])) {
            // Return the FIRST linked CSS file from the JS entry
            return $this->assetPath("{$dir}/{$chunk['css'][0]}");
        }

        return $this->assetPath("{$dir}/{$chunk['file']}");
    }

    /**
     * Handle non-JS asset chunks (images, fonts, etc.) from the manifest.
     * Simply resolves and returns the asset path from the chunk.
     */
    protected function handleAsset(array $chunk, string $dir): string
    {
        return $this->assetPath("{$dir}/{$chunk['file']}");
    }

    /*
     * Determine if the given asset path corresponds to a JavaScript file.
     */
    protected function isJsFile($asset): bool
    {
        $path = parse_url($asset, PHP_URL_PATH);

        return str_ends_with($path, '.js');
    }

    /*
     * Determine if the given asset $handle contains vite name.
     */
    protected function isViteAsset(string $asset): bool
    {
        return str_contains($asset, 'vite');
    }
}
