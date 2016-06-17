<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Router;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

/**
 * Specialized prototypes factory for use with RoutePluginManager.
 */
class RoutePrototypesFactory implements FactoryInterface
{
    /**
     * @var RoutePluginManager
     */
    protected $routePluginManager;

    /**
     * @var array
     */
    protected $prototypes = [];

    public function __construct(RoutePluginManager $routePluginManager)
    {
        $this->routePluginManager = $routePluginManager;
    }

    public function setPrototype($name, $prototype)
    {
        $this->prototypes[$name] = $prototype;
    }

    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        if (!isset($this->prototypes[$requestedName])) {
            return;
        }
        $route = $this->prototypes[$requestedName];
        if (isset($route[0])) {
            $type = $route[0];
            unset($route[0]);
        } elseif (isset($route['type'])) {
            $type = $route['type'];
            unset($route['type']);
        } else {
            throw new Exception\InvalidArgumentException('Missing "type" option');
        }
        return $this->routePluginManager->get($type, $route);
    }
}
