<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Router;

interface RouteStackInterface extends RouteInterface
{
    /**
     * @return RouteInterface
     */
    public function getRouter();

    /**
     * @param RouteInterface|Traversable|array|string $route
     * @return self
     */
    public function setRouter($route);

    /**
     * Add a route to the stack.
     *
     * @param  string  $name
     * @param  mixed   $route
     * @param  int $priority
     * @return RouteStackInterface
     */
    public function addRoute($name, $route, $priority = null);

    /**
     * Add multiple routes to the stack.
     *
     * @param  array|\Traversable $routes
     * @return RouteStackInterface
     */
    public function addRoutes($routes);

    /**
     * Remove a route from the stack.
     *
     * @param  string $name
     * @return RouteStackInterface
     */
    public function removeRoute($name);

    /**
     * Remove all routes from the stack and set new ones.
     *
     * @param  array|\Traversable $routes
     * @return RouteStackInterface
     */
    public function setRoutes($routes);

    /**
     * @return PriorityList
     */
    public function getRoutes();

    /**
     * @param string $name
     * @return RouteInterface
     */
    public function getRoute($name);

    public function setChains($routes);
    public function addChains($routes);
    public function addChain($name, $route, $priority = null);
    public function removeChain($name);
    public function getChain($name);
    public function getChains();
}
