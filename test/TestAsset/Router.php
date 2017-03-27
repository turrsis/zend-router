<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Router\TestAsset;

use Zend\Router\RouteStackInterface;
use Zend\Stdlib\RequestInterface as Request;

class Router implements RouteStackInterface
{
    /**
     * Create a new route with given options.
     *
     * @param  array|\Traversable $options
     * @return void
     */
    public static function factory($options = [])
    {
        return new static();
    }

    /**
     * Match a given request.
     *
     * @param  Request $request
     * @return RouteMatch|null
     */
    public function match(Request $request)
    {
    }

    /**
     * Assemble the route.
     *
     * @param  array $params
     * @param  array $options
     * @return mixed
     */
    public function assemble(array $params = [], array $options = [])
    {
    }

    /**
     * Add a route to the stack.
     *
     * @param  string  $name
     * @param  mixed   $route
     * @param  int $priority
     * @return RouteStackInterface
     */
    public function addRoute($name, $route, $priority = null)
    {
    }

    /**
     * Add multiple routes to the stack.
     *
     * @param  array|\Traversable $routes
     * @return RouteStackInterface
     */
    public function addRoutes($routes)
    {
    }

    /**
     * Remove a route from the stack.
     *
     * @param  string $name
     * @return RouteStackInterface
     */
    public function removeRoute($name)
    {
    }

    /**
     * Remove all routes from the stack and set new ones.
     *
     * @param  array|\Traversable $routes
     * @return RouteStackInterface
     */
    public function setRoutes($routes)
    {
    }

    public function addChain($name, $route, $priority = null)
    {
    }

    public function addChains($routes)
    {
    }

    public function getAssembledParams()
    {
    }

    public function getChain($name)
    {
    }

    public function getChains()
    {
    }

    public function getRouter()
    {
    }

    public function removeChain($name)
    {
    }

    public function setChains($routes)
    {
    }

    public function setRouter($route)
    {
    }

    public function getRoute($name)
    {
    }

    public function getRoutes()
    {
    }
}
