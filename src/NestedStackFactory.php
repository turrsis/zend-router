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
use Zend\Router\NestedStack;

class NestedStackFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $routeName, array $options = null)
    {
        return NestedStack::factory($options ?: []);
    }

    public function createService(ServiceLocatorInterface $container, $normalizedName = null, $routeName = null)
    {
        $routeName = $routeName ?: RouteInterface::class;
        return $this($container, $routeName, $this->creationOptions);
    }
}
