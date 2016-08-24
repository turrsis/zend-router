<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Router;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Router;
use Zend\Http\Request as Request;

class RouteStackLazyTest extends TestCase
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Router\RouteStack
     */
    protected $stack;
    
    protected $prototypes;

    public function setUp()
    {
        $this->request = new Request();
        $this->stack = Router\RouteStack::factory([
            'routes' => [
                'preRoute' => [
                    'router' => [
                        'segment',
                        'route'       => '/:foo',
                    ],
                    'routes'      => [
                        'lazy' => [
                            Router\RouteStackLazy::class,
                            'lazy_stack' => 'lazyRoute',
                        ],
                    ],
                ],
            ],
        ]);
        $this->prototypes = [
            'lazyRoute' => [
                Router\RouteStack::class,
                'routes'      => [
                    'literal1' => [
                        Router\Http\Literal::class,
                        'route' => '/bar',
                        'defaults' => ['literal1' => 'literal1Value'],
                    ],
                    'literal2' => [
                        Router\Http\Literal::class,
                        'route' => '/baz',
                        'defaults' => ['literal2' => 'literal2Value'],
                    ],
                ],
            ],
        ];
    }

    protected function stackMatch($uriPath)
    {
        $this->request->setUri($uriPath);
        return $this->stack->match($this->request);
    }

    public function testMatchWithoutLazy()
    {
        $routeMatch = $this->stackMatch('/test/bar');
        $this->assertEquals(9,               $routeMatch->getLength());
        $this->assertEquals('preRoute/lazy', $routeMatch->getMatchedRouteName());
        $this->assertEquals('test',          $routeMatch->getParam('foo'));
        $this->assertCount (2,               $routeMatch->getParams());

        $routeMatch = $this->stackMatch('/test/baz');
        $this->assertEquals(9,               $routeMatch->getLength());
        $this->assertEquals('preRoute/lazy', $routeMatch->getMatchedRouteName());
        $this->assertEquals('test',          $routeMatch->getParam('foo'));
        $this->assertCount (2,               $routeMatch->getParams());
    }

    public function testMatchWithLazy()
    {
        $this->stack->getRoutePluginManager()->configure(['prototypes' => $this->prototypes]);

        $routeMatch = $this->stackMatch('/test/bar');
        $this->assertEquals(9, $routeMatch->getLength());
        $this->assertEquals(
            'preRoute/lazy/literal1',
            $routeMatch->getMatchedRouteName()
        );
        $this->assertEquals(
            ['foo' => 'test', 'literal1' => 'literal1Value'],
            $routeMatch->getParams()
        );
        
        $routeMatch = $this->stackMatch('/test/baz');
        $this->assertEquals(9, $routeMatch->getLength());
        $this->assertEquals(
            'preRoute/lazy/literal2',
            $routeMatch->getMatchedRouteName()
        );
        $this->assertEquals(
            ['foo' => 'test', 'literal2' => 'literal2Value'],
            $routeMatch->getParams()
        );
    }
    
    public function testAssembleWithoutLazy()
    {
        $this->setExpectedException(
            Router\Exception\InvalidArgumentException::class,
            'Сan not be assembled without lazy route'
        );
        $this->stack->assemble(['foo' => 'bat'], ['name' => 'preRoute/lazy/literal1']);
    }

    public function testAssembleWithLazy()
    {
        $this->stack->getRoutePluginManager()->configure(['prototypes' => $this->prototypes]);

        $this->assertEquals(
            '/bat/bar', 
            $this->stack->assemble(['foo' => 'bat'], ['name' => 'preRoute/lazy/literal1'])
        );

        $this->assertEquals(
            '/bat/baz', 
            $this->stack->assemble(['foo' => 'bat'], ['name' => 'preRoute/lazy/literal2'])
        );
    }

    public function testRootRouteStackBaseUrlIsEmpty()
    {
        $stack = Router\RouteStack::factory([
            'router' => [
                Router\RouteStackLazy::class,
                'lazy_stack' => 'lazyRoute',
            ],
        ]);
        $stack->getRoutePluginManager()->configure([
            'delegators' => [
                'lazyRoute' => [function ($container, $name, $creationCallback, $options) {
                    $router = call_user_func($creationCallback);
                    $router->setBaseUrl('/foo/bar');
                    return $router;
                }],
            ],
            'prototypes' => [
                'lazyRoute' => [
                    Router\RootRouteStack::class,
                ],
            ],
        ]);

        $lazy = $stack->getRouter();

        $getLazyStack = new \ReflectionMethod($lazy, 'getLazyStack');
        $getLazyStack->setAccessible(true);

        $this->assertEquals(
            '',
            $getLazyStack->invoke($lazy)->getBaseUrl()
        );
    }
}
