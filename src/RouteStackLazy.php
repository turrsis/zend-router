<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Zend\Router;

use Zend\Stdlib\RequestInterface;
use Traversable;
use Zend\Stdlib\ArrayUtils;
use Zend\Router\Http\RouteMatch as HttpRouteMatch;

/**
 * Description of RouteStackLazy
 *
 * @author Shiri
 */
class RouteStackLazy implements RouteStackInterface
{
    /**
     * @var RouteStackInterface
     */
    protected $lazyStack = 'Router';

    protected $matchCount = 2;

    protected $lazyName = '__wrapper__';

    protected $lazyPathOffset;

    protected $lazyOptions;

    /**
     * @var RoutePluginManager
     */
    protected $routePluginManager;

    public static function factory($options = array())
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (!is_array($options)) {
            throw new Exception\InvalidArgumentException(__METHOD__ . ' expects an array or Traversable set of options');
        }

        if (!isset($options['route_plugins'])) {
            throw new Exception\InvalidArgumentException('"route_plugins" option is required and can not be null');
        }

        $instance = new self();

        $instance->routePluginManager = $options['route_plugins'];

        if (isset($options['lazy_stack'])) {
            $instance->lazyStack = $options['lazy_stack'];
        }

        if (isset($options['max_match_count'])) {
            $instance->matchCount = (int)$options['max_match_count'];
        }

        return $instance;
    }

    protected function getLazyStack()
    {
        if (!is_string($this->lazyStack)) {
            return $this->lazyStack;
        }

        if ($this->lazyStack == 'Router') {
            //!!!
            $refl = new \ReflectionProperty($this->routePluginManager, 'creationContext');
            $refl->setAccessible(true);
            $container = $refl->getValue($this->routePluginManager);
            if ($container->has($this->lazyStack)) {
                $this->lazyStack = $container->get($this->lazyStack);
                if ($this->lazyStack instanceof RootRouteStack) {
                    $this->lazyStack->setBaseUrl('');
                }
                return $this->lazyStack;
            }
        }
        if ($this->routePluginManager->has($this->lazyStack)) {
            $this->lazyStack = $this->routePluginManager->get($this->lazyStack);
            if ($this->lazyStack instanceof RootRouteStack) {
                $this->lazyStack->setBaseUrl('');
            }
            return $this->lazyStack;
        }
    }

    public function match(RequestInterface $request, $pathOffset = null, array $options = [])
    {
        $router = $this->getLazyStack();
        if ($router) {
            $match = $router->match(
                $request,
                $this->lazyPathOffset !== null ? $this->lazyPathOffset : $pathOffset,
                $this->lazyOptions    !== null ? $this->lazyOptions    : $options
            );
            $this->lazyPathOffset = null;
            $this->lazyOptions = null;
            return $match;
        }

        if ($this->matchCount-- <= 0) {
            return;
        }

        $this->lazyPathOffset = $pathOffset;
        $this->lazyOptions    = $options;

        return new HttpRouteMatch(
            array($this->lazyName => $this),
            strlen($request->getUri()->getPath()) - (int)$pathOffset
        );
    }

    public function assemble(array $params = array(), array $options = array())
    {
        if (!$this->getLazyStack()) {
            throw new Exception\InvalidArgumentException('Сan not be assembled without lazy route');
        }
        $options['only_return_path'] = true;
        return $this->lazyStack->assemble($params, $options);
    }

    public function getRouter()
    {
        if (!$this->getLazyStack()) {
            return null;
        }
        return $this->lazyStack->getRouter();
    }

    public function addChain($name, $route, $priority = null)
    {
        if (!$this->getLazyStack()) {
            return null;
        }
        return $this->lazyStack->addChain($name, $route, $priority);
    }

    public function addChains($routes)
    {
        if (!$this->getLazyStack()) {
            return null;
        }
        return $this->lazyStack->addChains($routes);
    }

    public function addRoute($name, $route, $priority = null)
    {
        if (!$this->getLazyStack()) {
            return null;
        }
        return $this->lazyStack->addRoute($name, $route, $priority);
    }

    public function addRoutes($routes)
    {
        if (!$this->getLazyStack()) {
            return null;
        }
        return $this->lazyStack->addRoutes($routes);
    }

    public function getAssembledParams()
    {
        if (!$this->getLazyStack()) {
            return null;
        }
        return $this->lazyStack->getAssembledParams();
    }

    public function getChain($name)
    {
        if (!$this->getLazyStack()) {
            return null;
        }
        return $this->lazyStack->getChain($name);
    }

    public function getChains()
    {
        if (!$this->getLazyStack()) {
            return null;
        }
        return $this->lazyStack->getChains();
    }

    public function getRoute($name)
    {
        if (!$this->getLazyStack()) {
            return null;
        }
        return $this->lazyStack->getRoute($name);
    }

    public function getRoutes()
    {
        if (!$this->getLazyStack()) {
            return null;
        }
        return $this->lazyStack->getRoutes();
    }

    public function removeChain($name)
    {
        if (!$this->getLazyStack()) {
            return null;
        }
        return $this->lazyStack->removeChain($name);
    }

    public function removeRoute($name)
    {
        if (!$this->getLazyStack()) {
            return null;
        }
        return $this->lazyStack->removeRoute($name);
    }

    public function setChains($routes)
    {
        if (!$this->getLazyStack()) {
            return null;
        }
        return $this->lazyStack->setChains($routes);
    }

    public function setRoutes($routes)
    {
        if (!$this->getLazyStack()) {
            return null;
        }
        return $this->lazyStack->setRoutes($routes);
    }

    public function setRouter($route)
    {
        if (!$this->getLazyStack()) {
            return null;
        }
        return $this->lazyStack->setRouter($route);
    }
}
