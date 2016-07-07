<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Zend\Router;

use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\RequestInterface;
use Zend\Stdlib\ArrayUtils;
use Traversable;

/**
 * Description of Route
 *
 * @author Shiri
 */
class RouteStack implements RouteStackInterface
{
    /**
     * @var RouteInterface
     */
    protected $router;

    /**
     * @var PriorityList
     */
    protected $routes;

    /**
     * @var PriorityList
     */
    protected $chains;

    /**
     * Default parameters.
     *
     * @var array
     */
    protected $defaults = [];

    /**
     * Whether the route may terminate.
     *
     * @var bool
     */
    protected $mayTerminate;

    /**
     * Route plugin manager
     *
     * @var RoutePluginManager
     */
    protected $routePluginManager;

    /**
     * List of assembled parameters.
     *
     * @var array
     */
    protected $assembledParams = [];

    public function __construct()
    {
        $this->routes = new PriorityList(function ($item) {
            return RouteStack::factory($item, $this);
        });
        $this->chains = new PriorityList(function ($item) {
            $route = $this->factoryRouter($item);
            if ($route instanceof RouteStackInterface) {
                throw new Exception\InvalidArgumentException('chain route not accept RouteStackInterface');
            }
            return $route;
        });
    }

    /**
     * assemble(): defined by \Zend\Router\RouteInterface interface
     * 
     * @param array $params
     * @param array $options
     * @return string
     * @throws Exception\RuntimeException
     */
    public function assemble(array $params = [], array $options = [])
    {
        $this->assembledParams = [];
        $path = '';
        $name = null;
        $options['has_child'] = false;
        if (isset($options['name'])) {
            if (!is_array($options['name'])) {
                $options['name'] = explode('/', $options['name']);
            }
            if ($options['name']) {
                $name = array_shift($options['name']);
            }
            if (!$options['name']) {
                unset($options['name']);
            }
            $options['has_child'] = $name != null;
        }

        if (isset($options['translator']) && !isset($options['locale']) && isset($params['locale'])) {
            $options['locale'] = $params['locale'];
        }

        if (!isset($options['params'])) {
            $options['params'] = $params;
        }

        if ($this->router) {
            $path .= $this->getRouter()->assemble($params, $options);
            $this->assembledParams = array_merge($this->assembledParams, $this->getRouter()->getAssembledParams());
            $params = array_diff_key($params, array_flip($this->getRouter()->getAssembledParams()));
            if (!$name) {
                if ($this->routes->count() && !$this->mayTerminate) {
                    throw new Exception\RuntimeException('RouteStack route with childs may not terminate');
                } elseif (!$this->chains->count()) {
                    return $path;
                }
            }
        }
        if ($this->chains->count()) {
            $chainI = 0;
            $chainCount = $this->chains->count();

            $chainOptions = $options;
            unset($chainOptions['name']);
            foreach ($this->chains as $chain) {
                $chainOptions['has_child'] = (isset($options['has_child']) ? $options['has_child'] : false) || (++$chainI === $chainCount);

                $path   .= $chain->assemble($params, $chainOptions);
                $params  = array_diff_key($params, array_flip($chain->getAssembledParams()));
                $this->assembledParams = array_merge($this->assembledParams, $chain->getAssembledParams());
            }
        }
        unset($options['has_child']);
        $options['only_return_path'] = true;

        if ($name && !$this->routes->count()) {
            throw new Exception\RuntimeException(sprintf('Route with name "%s" not exist', $name));
        }
        if ($this->routes->count()) {
            $route = $this->routes->get($name);

            if (!$route) {
                throw new Exception\RuntimeException(sprintf('Route with name "%s" not found', $name));
            }

            $params = array_merge($this->defaults, $params);
            if (!isset($options['params'])) {
                $options['params'] = $params;
            }

            $path .= $route->assemble($params, $options);
            $this->assembledParams = array_merge($this->assembledParams, $route->getAssembledParams());
        }

        return $path;
    }

    /**
     * match(): defined by \Zend\Router\RouteInterface
     * 
     * @param RequestInterface $request
     * @param int|null $pathOffset
     * @param array $options
     * @return null|Http\RouteMatch
     */
    public function match(RequestInterface $request, $pathOffset = null, array $options = [])
    {
        if (!method_exists($request, 'getUri')) {
            return;
        }

        $mustTerminate = ($pathOffset === null);
        if ($pathOffset === null) {
            $pathOffset = 0;
        }
        $pathLength = strlen($request->getUri()->getPath());
        $nextOffset = $pathOffset;

        $match = null;
        if ($this->router) {
            $match = $this->getRouter()->match($request, $pathOffset, $options);
            if (!$match) {
                return;
            }
            $options['match'] = $match;
            $nextOffset += $match->getLength();
        }

        if ($this->chains->count()) {
            $match      = $match ?: new Http\RouteMatch([]);
            foreach ($this->chains as $name => $route) {
                $chainMatch = $route->match($request, $nextOffset, $options);

                if (!$chainMatch instanceof Http\RouteMatch) {
                    return;
                }

                $match->merge($chainMatch);
                $nextOffset += $chainMatch->getLength();
            }

            if ($mustTerminate && $nextOffset !== $pathLength) {
                return;
            }
        }

        if ($this->routes->count()) {
            if ($this->mayTerminate && $nextOffset === $pathLength/* && ('' == trim($request->getUri()->getQuery()) || !false)*/) {
                if ($match) {
                    foreach ($this->defaults as $paramName => $value) {
                        if ($match->getParam($paramName) === null) {
                            $match->setParam($paramName, $value);
                        }
                    }
                }
                return $match;
            }
            $childMatch = null;
            foreach ($this->routes as $name => $route) {
                $childMatch = $route->match($request, $nextOffset, $options);
                if (!$childMatch instanceof Http\RouteMatch) {
                    continue;
                }

                $nextOffset += $childMatch->getLength();
                if (!$match) {
                    $match = $childMatch->setMatchedRouteName($name);
                } elseif ($match->getLength() + $childMatch->getLength() + $pathOffset === $pathLength) {
                    $match->merge($childMatch)->setMatchedRouteName($name);
                } else {
                    $childMatch = null;
                }
                break;
            }
            if (!$childMatch) {
                return;
            }
            $options['match'] = $match;
        }

        if ($match) {
            foreach ($this->defaults as $paramName => $value) {
                if ($match->getParam($paramName) === null) {
                    $match->setParam($paramName, $value);
                }
            }
        }
        return $match;
    }

    /**
     * 
     * @param array|self|string|RouteInterface $route
     * @param RouteStackInterface|null $context
     * @return self
     */
    public static function factory($route = [], RouteStackInterface $context = null)
    {
        $route = self::normalizeAndValidateRoute($route);

        if (is_string($route)) {
            $route = $context->getRoutePluginManager()->get($route);
        }

        if ($route instanceof RouteStackInterface) {
            return $route;
        }

        if ($route instanceof RouteInterface) {
            $instance = new static();
            return $instance->setRouter($route);
        }
        if (!isset($route['route_plugins'])) {
            $route['route_plugins'] = $context instanceof self 
                    ? $context->getRoutePluginManager()
                    : new RoutePluginManager(new ServiceManager());
        }

        $nodeType = self::resolveTypeFromArray($route);
        if ($nodeType) {
            return $route['route_plugins']->get($nodeType, $route);
        }

        $instance = new static();

        if (isset($route['may_terminate'])) {
            $instance->mayTerminate = (bool)$route['may_terminate'];
        }
        if (isset($route['route_plugins'])) {
            $instance->routePluginManager = $route['route_plugins'];
        }
        if (isset($route['prototypes'])) {
            $instance->routePluginManager->configure(['prototypes' => $route['prototypes']]);
        }
        if (isset($route['defaults'])) {
            $instance->setDefaults($route['defaults']);
        }

        if (isset($route['routes'])) {
            $instance->setRoutes($route['routes']);
        }
        if (isset($route['chains'])) {
            $instance->setChains($route['chains']);
        }
        if (isset($route['router'])) {
            $instance->setRouter($route['router']);
        }
        return $instance;
    }

    protected static function resolveTypeFromArray(&$route, $type = null)
    {
        if (isset($route[0])) {
            $type = $route[0];
            unset($route[0]);
        } elseif (isset($route['type'])) {
            $type = $route['type'];
            unset($route['type']);
        }
        return $type;
    }

    protected static function normalizeAndValidateRoute($route)
    {
        if (!$route) {
            return null;
        }

        if ($route instanceof RouteInterface) {
            return $route;
        }

        if ($route instanceof Traversable) {
            return ArrayUtils::iteratorToArray($route);
        }

        if (is_string($route)) {
            return $route;
        }

        if (is_array($route)) {
            if (isset($route['options'])) { // BC config compatibility
                $options = $route['options'];
                unset($route['options']);
                $route = ArrayUtils::merge($route, $options);
            }
            return $route;
        }

        throw new Exception\InvalidArgumentException(sprintf(
            '$route should be %s or %s or %s. %s given.',
            RouteInterface::class,
            Traversable::class,
            'array',
            is_object($route) ? get_class($route) : gettype($route)
        ));
    }

    /**
     * Get the route plugin manager.
     *
     * @return RoutePluginManager
     */
    public function getRoutePluginManager()
    {
        return $this->routePluginManager;
    }

    /**
     * @param RoutePluginManager $routePlugins
     * @return self
     */
    public function setRoutePluginManager(RoutePluginManager $routePlugins)
    {
        $this->routePluginManager = $routePlugins;
        return $this;
    }

    /**
     * @return array
     */
    public function getAssembledParams()
    {
        return $this->assembledParams;
    }

    /**
     * @param RouteInterface|Traversable|array|string $route
     * @return self
     */
    public function setRouter($route)
    {
        $this->router = self::normalizeAndValidateRoute($route);

        return $this;
    }

    protected function factoryRouter($router = [])
    {
        if ($router instanceof RouteInterface) {
            return $router;
        }

        if (is_string($router)) {
            return $this->routePluginManager->has($router)
                ? $this->routePluginManager->get($router)
                : null;
        }

        $router = self::normalizeAndValidateRoute($router);

        if (!isset($router['route_plugins'])) {
            $router['route_plugins'] = $this->routePluginManager;
        }

        $type = self::resolveTypeFromArray($router);
        if (!$type) {
            throw new Exception\InvalidArgumentException('Missing "type" option');
        }
        return $this->getRoutePluginManager()->get($type, $router);
    }

    /**
     * @return RouteInterface
     */
    public function getRouter()
    {
        if (!$this->router || $this->router instanceof RouteInterface) {
            return $this->router;
        }

        $this->router = $this->factoryRouter($this->router);
        return $this->router;
    }

    /**
     * @return PriorityList
     */
    public function getRoutes()
    {
        return $this->routes;
    }

    /**
     * @param string $name
     * @return RouteInterface
     */
    public function getRoute($name)
    {
        return $this->routes->get($name);
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasRoute($name)
    {
        return $this->routes->has($name);
    }

    /**
     * @param array|Traversable $routes
     * @return self
     */
    public function setRoutes($routes)
    {
        $this->routes->clear();
        return $this->addRoutes($routes);
    }

    /**
     * @param array|Traversable $routes
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function addRoutes($routes)
    {
        if (!is_array($routes) && !$routes instanceof Traversable) {
            throw new Exception\InvalidArgumentException('addChildRoutes expects an array or Traversable set of routes');
        }
        foreach ($routes as $name => $route) {
            $this->addRoute($name, $route);
        }
        return $this;
    }

    /**
     * @param type $name
     * @param type $route
     * @param type $priority
     * @return \Zend\Router\RouteStack
     */
    public function addRoute($name, $route, $priority = null)
    {
        $route = self::normalizeAndValidateRoute($route);
        if (is_array($route) && isset($route['priority'])) {
            $priority = $route['priority'];
            unset($route['priority']);
        }
        $this->routes->insert($name, $route, $priority);
        return $this;
    }

    /**
     * @param string $name
     * @return self
     */
    public function removeRoute($name)
    {
        $this->routes->remove($name);
        return $this;
    }

    /**
     * @param string $name
     * @return bool
     */
    /*public function hasChildRoute($name)
    {
        return $this->childRoutes->has($name);
    }*/

    /**
     * @return PriorityList
     */
    public function getChains()
    {
        return $this->chains;
    }

    /**
     * @param type $name
     * @return self
     */
    public function getChain($name)
    {
        return $this->chains->get($name);
    }

    /**
     * @param array|Traversable $routes
     * @return self
     */
    public function setChains($routes)
    {
        $this->chains->clear();
        return $this->addChains($routes);
    }

    /**
     * @param array|Traversable $routes
     * @return self
     * @throws Exception\InvalidArgumentException
     */
    public function addChains($routes)
    {
        if (!is_array($routes) && !$routes instanceof Traversable) {
            throw new Exception\InvalidArgumentException('addChainRoutes expects an array or Traversable set of routes');
        }
        foreach ($routes as $name => $route) {
            $this->addChain($name, $route);
        }
        return $this;
    }

    /**
     * @param string $name
     * @param string|array|RouteInterface|Traversable $route
     * @param int $priority
     * @return self
     */
    public function addChain($name, $route, $priority = null)
    {
        $route = self::normalizeAndValidateRoute($route);
        if (is_array($route) && isset($route['priority'])) {
            $priority = $route['priority'];
            unset($route['priority']);
        }
        $this->chains->insert($name, $route, $priority);
        return $this;
    }

    /**
     * @param string $name
     * @return self
     */
    public function removeChain($name)
    {
        $this->chains->remove($name);
        return $this;
    }

    /**
     * @param string $name
     * @return bool
     */
    /*public function hasChainRoute($name)
    {
        return $this->chainRoutes->has($name);
    }*/

    /**
     * Set a default parameters.
     *
     * @param  array $params
     * @return self
     */
    public function setDefaults(array $defaults)
    {
        $this->defaults = $defaults;
        return $this;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function setDefault($name, $value)
    {
        $this->defaults[$name] = $value;
        return $this;
    }
}
