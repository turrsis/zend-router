<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Router;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class RouterFactory implements FactoryInterface
{
    /**
     * Create and return the router
     *
     * Delegates to the HttpRouter service.
     *
     * @param  ContainerInterface $container
     * @param  string $name
     * @param  null|array $options
     * @return RouteStackInterface
     */
    public function __invoke(ContainerInterface $container, $name, array $options = null)
    {
        $config = [];
        if ($container->has('config')) {
            $config = $container->get('config');
            $config = isset($config['router']) ? $config['router'] : [];
        }

        // Obtain the configured router class, if any
        $class  = RootRouteStack::class;
        if (isset($config['router_class']) && class_exists($config['router_class'])) {
            $class = $config['router_class'];
        }

        // Inject the route plugins
        if (! isset($config['route_plugins'])) {
            $routePluginManager = $container->get('RoutePluginManager');
            $config['route_plugins'] = $routePluginManager;
        }

        // Obtain an instance
        $factory = sprintf('%s::factory', $class);
        return call_user_func($factory, $config);
    }

    /**
     * Create and return RouteStackInterface instance
     *
     * For use with zend-servicemanager v2; proxies to __invoke().
     *
     * @param ServiceLocatorInterface $container
     * @param null|string $normalizedName
     * @param null|string $requestedName
     * @return RouteStackInterface
     */
    public function createService(ServiceLocatorInterface $container, $normalizedName = null, $requestedName = null)
    {
        $requestedName = $requestedName ?: 'Router';
        return $this($container, $requestedName);
    }
}
