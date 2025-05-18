<?php

declare(strict_types=1);

namespace YevheniiVolosiuk\ViteWithWordPress\ViteBase;

/**
 * Facade class for the ViteBase service.
 *
 * This class provides a static proxy interface to the underlying ViteBase instance,
 * allowing you to call methods statically on `Vite::` which are forwarded to the singleton
 * instance of `ViteBase`. It simplifies usage by avoiding the need to manually
 * instantiate or inject the service.
 *
 * Inspired by Laravel's Facade pattern, but with a minimal and straightforward implementation.
 *
 * Example usage:
 *   Vite::asset('resources/js/main.js');
 *
 * This delegates the `asset` method call to the underlying `ViteBase` instance.
 */
class Vite
{
    /**
     * Handle static method calls by forwarding to the instance of ViteBase::class.
     */
    public static function __callStatic($method, $args)
    {
        $instance = ViteBase::getInstance();

        if (! method_exists($instance, $method)) {
            throw new \BadMethodCallException("Method {$method} does not exist in ".static::class);
        }

        return $instance->$method(...$args);
    }
}
