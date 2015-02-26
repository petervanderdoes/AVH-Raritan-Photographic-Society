<?php
/**
 * Created by PhpStorm.
 * User: pdoes
 * Date: 2/26/15
 * Time: 2:27 PM
 */

namespace RpsCompetition;

use Illuminate\Container\Container;

class Application extends Container
{
    /**
     * All of the registered service providers.
     *
     * @var array
     */
    protected $serviceProviders = [];

    /**
     * Get the registered service provider instance if it exists.
     *
     * @param  \Illuminate\Support\ServiceProvider|string $provider
     *
     * @return \Illuminate\Support\ServiceProvider|null
     */
    public function getProvider($provider)
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return array_first(
            $this->serviceProviders,
            function ($key, $value) use ($name) {
                return $value instanceof $name;
            }
        );
    }

    /**
     * Register a service provider with the application.
     *
     * @param  \Illuminate\Support\ServiceProvider|string $provider
     * @param  bool                                       $force
     *
     * @return \Illuminate\Support\ServiceProvider
     */
    public function register($provider, $force = false)
    {
        if ($registered = $this->getProvider($provider) && !$force) {
            return $registered;
        }

        // If the given "provider" is a string, we will resolve it, passing in the
        // application instance automatically for the developer. This is simply
        // a more convenient way of specifying your service provider classes.
        if (is_string($provider)) {
            $provider = $this->resolveProviderClass($provider);
        }

        $provider->register();

        return $provider;
    }

    /**
     * Resolve a service provider instance from the class name.
     *
     * @param  string $provider
     *
     * @return \Illuminate\Support\ServiceProvider
     */
    public function resolveProviderClass($provider)
    {
        return new $provider($this);
    }
}
