<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Router;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Router;
use Zend\Router\RouteStack;
use Zend\Router\RoutePluginManager;
use Zend\ServiceManager\ServiceManager;
use Zend\Http\Request as Request;
use Zend\Stdlib\Request as BaseRequest;
use Zend\Stdlib\Parameters;

class RouteStackTest extends TestCase
{
    /**
     * @var RoutePluginManager
     */
    protected $routePluginManager;

    public function setUp()
    {
        $this->routePluginManager = new RoutePluginManager(new ServiceManager());
    }
    
    public function testFactoryRouteAsArray()
    {
        $router = RouteStack::factory([
            'router' => [
                Router\Http\Literal::class,
                'route' => '/foo',
            ],
        ]);
        $route = $router->getRouter();
        $this->assertEquals('/foo', $this->readAttribute($route, 'route'));
        $this->assertInstanceOf(Router\Http\Literal::class, $route);
    }

    public function testFactoryRouteAsPrototype()
    {
        $stack = RouteStack::factory([
            'router' => 'prototype-route',
            'routes' => [
                'child-1' => 'prototype-node',
                'child-2' => 'prototype-node-instance',
            ],
            'prototypes' => [
                'prototype-route' => [
                    Router\Http\Literal::class,
                    'route' => '/foo',
                ],
                'prototype-node' => [
                    RouteStack::class,
                    'router' => [
                        Router\Http\Segment::class,
                        'route' => '/foo-regex',
                    ],
                    'routes' => [
                        'prototype-node-child' => [
                            RouteStack::class,
                            'router' => Router\Http\Wildcard::class,
                        ],
                    ],
                ],
                'prototype-node-instance' => [
                    RouteStack::class,
                    'router' => new Router\Http\Regex('xxx', ['xxx'=>'zzz'])
                ],
            ],
        ]);
        $route = $stack->getRouter();
        $this->assertEquals('/foo', $this->readAttribute($route, 'route'));
        $this->assertInstanceOf(Router\Http\Literal::class, $route);

        $this->assertInstanceOf(
            Router\RouteStack::class,
            $stack->getRoute('child-1')
        );

        $this->assertInstanceOf(
            Router\Http\Segment::class,
            $stack->getRoute('child-1')->getRouter()
        );

        $this->assertInstanceOf(
            Router\RouteStack::class,
            $stack->getRoute('child-1')->getRoute('prototype-node-child')
        );

        $this->assertInstanceOf(
            Router\Http\Wildcard::class,
            $stack->getRoute('child-1')->getRoute('prototype-node-child')->getRouter()
        );
        
        $this->assertInstanceOf(
            Router\RouteStack::class,
            $stack->getRoute('child-2')
        );

        $this->assertInstanceOf(
            Router\Http\Regex::class,
            $stack->getRoute('child-2')->getRouter()
        );
    }

    public function testFactoryRouteAsClassName()
    {
        $stack = RouteStack::factory([
            'router' => Router\Http\Wildcard::class,
        ]);
        $this->assertInstanceOf(Router\Http\Wildcard::class, $stack->getRouter());
    }

    public function testFactoryRouteAsArrayObject()
    {
        $stack = RouteStack::factory([
            'router' => new \ArrayObject([
                Router\Http\Literal::class,
                'route' => '/foo',
            ])
        ]);
        $route = $stack->getRouter();
        $this->assertEquals('/foo', $this->readAttribute($route, 'route'));
        $this->assertInstanceOf(Router\Http\Literal::class, $route);
    }

    public function testFactoryRouteAsObject()
    {
        $dummy = new TestAsset\DummyRoute;
        $stack = RouteStack::factory([
            'router' => $dummy
        ]);
        $this->assertSame($dummy, $stack->getRouter());
    }

    public function testFactoryChildRoutes()
    {
        $dummy1 = new TestAsset\DummyRoute;
        $dummy2 = new TestAsset\DummyRoute;
        $stack = RouteStack::factory([
            'routes' => [
                'className' => ['router' => Router\Http\Wildcard::class],
                'array' => ['router' => [
                    Router\Http\Literal::class,
                    'route'    => '/array',
                ]],
                'ArrayObject' => new \ArrayObject(['router' => [
                    'Zend\Router\Http\Literal',
                    'route' => '/array_object-bar',
                ]]),
                'DummyRoute' => ['router' => $dummy1],
                'prototype1' => ['router' => 'bar'],
                'prototype2' => ['router' => 'bar'],
            ],
            'prototypes' => [
                'bar' => [
                    Router\Http\Literal::class,
                    'route' => '/prototype-bar',
                ],
            ],
        ]);

        foreach ($stack->getRoutes() as $child) {
            $this->assertInstanceOf(RouteStack::class, $child);
        }

        $route = $stack->getRoute('className')->getRouter();
        $this->assertInstanceOf(Router\Http\Wildcard::class, $route);

        $route = $stack->getRoute('array')->getRouter();
        $this->assertEquals('/array', $this->readAttribute($route, 'route'));
        $this->assertInstanceOf(Router\Http\Literal::class, $route);

        $route = $stack->getRoute('ArrayObject')->getRouter();
        $this->assertEquals('/array_object-bar', $this->readAttribute($route, 'route'));
        $this->assertInstanceOf(Router\Http\Literal::class, $route);

        $route = $stack->getRoute('DummyRoute')->getRouter();
        $this->assertSame($dummy1, $route);

        $prototype1 = $stack->getRoute('prototype1')->getRouter();
        $this->assertEquals('/prototype-bar', $this->readAttribute($prototype1, 'route'));
        $this->assertInstanceOf(Router\Http\Literal::class, $prototype1);

        $prototype2 = $stack->getRoute('prototype2')->getRouter();
        $this->assertEquals('/prototype-bar', $this->readAttribute($prototype2, 'route'));
        $this->assertInstanceOf(Router\Http\Literal::class, $prototype2);

        $this->assertSame($prototype1, $prototype2);
    }

    public function testFactoryRoutesWithPriority()
    {
        $stack = RouteStack::factory([
            'routes'  => [
                'route1' => [
                    'priority' => 1,
                    'router' => [
                        Router\Http\Literal::class,
                        'route'    => '/array',
                    ],
                ],
                'route2' => new \ArrayObject([
                    'priority' => 2,
                    'router' => [
                        'Zend\Router\Http\Literal',
                        'route' => '/array_object-bar',
                    ],
                ]),
                'route3' => ['priority' => 3, 'router' => new TestAsset\DummyRoute],
                'route4' => ['priority' => 4, 'router' => 'bar'],
            ],
            'prototypes' => [
                'bar' => [
                    Router\Http\Literal::class,
                    'route' => '/prototype-bar',
                ],
            ],
        ]);
        $this->assertSame(
            ['route4', 'route3', 'route2', 'route1'],
            array_keys($stack->getRoutes()->toArray())
        );
    }

    public function testFactoryChains()
    {
        $dummy1 = new TestAsset\DummyRoute;
        $dummy2 = new TestAsset\DummyRoute;
        $stack = RouteStack::factory([
            'chains' => [
                'className' => [Router\Http\Wildcard::class],
                'array' => [
                    Router\Http\Literal::class,
                    'route'    => '/array',
                ],
                'ArrayObject' => new \ArrayObject([
                    'Zend\Router\Http\Literal',
                    'route' => '/array_object-bar',
                ]),
                'DummyRoute' => $dummy1,
                'prototype1' => 'bar',
                'prototype2' => 'bar',
            ],
            'prototypes' => [
                'bar' => [
                    Router\Http\Literal::class,
                    'route' => '/prototype-bar',
                ],
            ],
        ]);

        $route = $stack->getChain('className');
        $this->assertInstanceOf(Router\Http\Wildcard::class, $route);

        $route = $stack->getChain('array');
        $this->assertInstanceOf(Router\Http\Literal::class, $route);
        $this->assertEquals('/array', $this->readAttribute($route, 'route'));

        $route = $stack->getChain('ArrayObject');
        $this->assertInstanceOf(Router\Http\Literal::class, $route);
        $this->assertEquals('/array_object-bar', $this->readAttribute($route, 'route'));

        $route = $stack->getChain('DummyRoute');
        $this->assertSame($dummy1, $route);

        $prototype1 = $stack->getChain('prototype1');
        $this->assertInstanceOf(Router\Http\Literal::class, $prototype1);
        $this->assertEquals('/prototype-bar', $this->readAttribute($prototype1, 'route'));

        $prototype2 = $stack->getChain('prototype2');
        $this->assertInstanceOf(Router\Http\Literal::class, $prototype2);
        $this->assertEquals('/prototype-bar', $this->readAttribute($prototype2, 'route'));

        $this->assertSame($prototype1, $prototype2);
    }

    public function testFactoryChainsWithPriority()
    {
        $stack = RouteStack::factory([
            'chains'  => [
                'route1' => [
                    'priority' => 1,
                    'router' => [
                        Router\Http\Literal::class,
                        'route'    => '/array',
                    ],
                ],
                'route2' => new \ArrayObject([
                    'priority' => 2,
                    'router' => [
                        'Zend\Router\Http\Literal',
                        'route' => '/array_object-bar',
                    ],
                ]),
                'route3' => ['priority' => 3, 'router' => new TestAsset\DummyRoute],
                'route4' => ['priority' => 4, 'router' => 'bar'],
            ],
            'prototypes' => [
                'bar' => [
                    Router\Http\Literal::class,
                    'route' => '/prototype-bar',
                ],
            ],
        ]);
        $this->assertSame(
            ['route4', 'route3', 'route2', 'route1'],
            array_keys($stack->getChains()->toArray())
        );
    }

    public function testFactory()
    {
        $stack = RouteStack::factory([
            'defaults'      => ['p1'=>'v1'],
            'may_terminate' => true,
        ]);
        $this->assertEquals(['p1'=>'v1'], $this->readAttribute($stack, 'defaults'));
        $this->assertEquals(true, $this->readAttribute($stack, 'mayTerminate'));
    }

    public function testFactoryRouteStackType()
    {
        $stack   = RouteStack::factory([
            'type'  => TestAsset\DummyRouteStack::class,
            'router' => Router\Http\Wildcard::class,
        ]);
        $this->assertInstanceOf(TestAsset\DummyRouteStack::class, $stack);
    }

    public function testNoMatchWithoutUriMethod()
    {
        $stack = RouteStack::factory([
            'router' => [
                'Zend\Router\Http\Literal',
                'route'    => '/foo',
                'defaults' => ['controller' => 'foo'],
            ],
        ]);
        $this->assertNull($stack->match(new BaseRequest()));
    }

    /**
     * @group 3711
     */
    public function testMarkedAsMayTerminateCanMatchWhenQueryStringPresent()
    {
        $stack = RouteStack::factory([
            'router' => [
                'Zend\Router\Http\Literal',
                'route' => '/resource',
                'defaults' => [
                    'controller' => 'ResourceController',
                    'action'     => 'resource',
                ],
            ],
            'may_terminate' => true,
            'routes'  => [
                'child' => ['router' => [
                    'Zend\Router\Http\Literal',
                    'route' => '/child',
                    'defaults' => [
                        'action' => 'child',
                    ]],
                ],
            ],
        ]);
        $request = new Request();
        $request->setUri('http://example.com/resource?foo=bar');
        $request->setQuery(new Parameters(['foo' => 'bar']));

        $match = $stack->match($request);
        $this->assertInstanceOf('Zend\Router\RouteMatch', $match);
        $this->assertEquals('resource', $match->getParam('action'));
    }

    public function routeProvider()
    {
        $routePluginManager = $this->routePluginManager;
        $data1 = [
            'simple-route' => [
                'stack' => RouteStack::factory([
                    'router' => [
                        'Zend\Router\Http\Literal',
                        'route'    => '/foo',
                        'defaults' => ['fooKey' => 'fooVal'],
                    ],
                    'may_terminate' => true,
                ]),
                'data' => [
                    'simple-match' => [
                        '/foo',
                        null,
                        'expected' => [
                            'length' => null,
                            'name'   => null,
                            'params' => ['fooKey' => 'fooVal'],
                        ],
                    ],
                ],
            ],
            'only-childs' => [
                'stack' => RouteStack::factory([
                    'routes'  => [
                        'child1' => ['router' => [
                            'Zend\Router\Http\Literal',
                            'route'    => '/foo',
                            'defaults' => ['fooKey' => 'fooVal'],
                        ]],
                        'child2' => ['router' => [
                            'Zend\Router\Http\Literal',
                            'route'    => '/bar',
                            'defaults' => ['barKey' => 'barVal'],
                        ]],
                    ],
                ]),
                'data' => [
                    'simple-match1' => [
                        '/foo',
                        null,
                        'expected' => [
                            'length' => 4,
                            'name'   => 'child1',
                            'params' => ['fooKey' => 'fooVal'],
                        ],
                    ],
                    'simple-match2' => [
                        '/bar',
                        null,
                        'expected' => [
                            'length' => 4,
                            'name'   => 'child2',
                            'params' => ['barKey' => 'barVal'],
                        ],
                    ],
                ],
            ],
        ];
        $data2 = [
            'with-route-and-nested-childs' => [
                'stack' => RouteStack::factory([
                    'router' => [
                        'Zend\Router\Http\Literal',
                        'route'    => '/foo',
                        'defaults' => ['controller' => 'foo'],
                    ],
                    'may_terminate' => true,
                    'routes'  => [
                        'bar' => [
                            'router' => [
                                'Zend\Router\Http\Literal',
                                'route'    => '/bar',
                                'defaults' => ['controller' => 'bar'],
                            ]
                        ],
                        'baz' => [
                            'router' => [
                                'Zend\Router\Http\Literal',
                                'route' => '/baz',
                            ],
                            'routes' => [
                                'bat' => [
                                    'router' => [
                                        'Zend\Router\Http\Segment',
                                        'route' => '/:controller',
                                    ],
                                    'may_terminate' => true,
                                    'routes'  => [
                                        'wildcard' => ['router' => [
                                            'Zend\Router\Http\Wildcard'
                                        ]],
                                    ],
                                ],
                            ],
                        ],
                        'bat' => [
                            'router' => [
                                'Zend\Router\Http\Segment',
                                'route'    => '/bat[/:foo]',
                                'defaults' => ['foo' => 'bar'],
                            ],
                            'may_terminate' => true,
                            'routes'  => [
                                'literal' => [
                                    'router' => [
                                        'Zend\Router\Http\Literal',
                                        'route' => '/bar',
                                    ]
                                ],
                                'optional' => [
                                    'router' => [
                                        'Zend\Router\Http\Segment',
                                        'route' => '/bat[/:bar]',
                                    ]
                                ],
                            ],
                        ],
                    ],
                    'route_plugins' => $routePluginManager,
                ]),
                'data' => [
                    'Part-route-may-not-terminate' => [
                        '/foo',
                        null,
                        'expected' => [
                            'assemble',
                            'length' => null,
                            'name'   => 'baz',
                            'params' => [],
                            'exception' => [
                                'Zend\Router\Exception\RuntimeException',
                                'RouteStack route with childs may not terminate',
                            ],
                        ],
                    ],
                    'simple-match' => [
                        '/foo',
                        null,
                        'expected' => [
                            'length' => null,
                            'name'   => null,
                            'params' => ['controller' => 'foo']
                        ],
                    ],
                    'offset-skips-beginning' => [
                        '/bar/foo',
                        4,
                        'expected' => [
                            'length' => 4,
                            'name'   => null,
                            'params' => ['controller' => 'foo']
                        ],
                    ],
                    'simple-child-match' => [
                        '/foo/bar',
                        null,
                        'expected' => [
                            'length' => null,
                            'name'   => 'bar',
                            'params' => ['controller' => 'bar']
                        ],
                    ],
                    'offset-does-not-enable-partial-matching' => [
                        '/foo/foo',
                        null,
                        'expected' => null,
                    ],
                    'offset-does-not-enable-partial-matching-in-child' => [
                        '/foo/bar/baz',
                        null,
                        'expected' => null,
                    ],
                    'non-terminating-part-does-not-match' => [
                        '/foo/baz',
                        null,
                        'expected' => null,
                    ],
                    'child-of-non-terminating-part-does-match' => [
                        '/foo/baz/bat',
                        null,
                        'expected' => [
                            'length'  => null,
                            'name'    => 'baz/bat',
                            'params'  => ['controller' => 'bat']
                        ],
                    ],
                    'parameters-are-used-only-once' => [
                        '/foo/baz/wildcard/foo/bar',
                        null,
                        'expected' => [
                            'length' => null,
                            'name'   => 'baz/bat/wildcard',
                            'params' => ['controller' => 'wildcard', 'foo' => 'bar']
                        ],
                    ],
                    'optional-parameters-are-dropped-without-child' => [
                        '/foo/bat',
                        null,
                        'expected' => [
                            'length' => null,
                            'name'   => 'bat',
                            'params' => ['foo' => 'bar']
                        ],
                    ],
                    'optional-parameters-are-not-dropped-with-child' => [
                        '/foo/bat/bar/bar',
                        null,
                        'expected' => [
                            'length' => null,
                            'name'   => 'bat/literal',
                            'params' => ['foo' => 'bar']
                        ],
                    ],
                    'optional-parameters-not-required-in-last-part' => [
                        '/foo/bat/bar/bat',
                        null,
                        'expected' => [
                            'length' => null,
                            'name'   => 'bat/optional',
                            'params' => ['foo' => 'bar']
                        ],
                    ],
                    'get-AssembledParams' => [
                        '/foo/baz/foo',
                        null,
                        'expected' => [
                            'length' => null,
                            'name'   => 'baz/bat',
                            'params' => ['controller' => 'foo'],
                            'assembled_params' => ['controller'],
                        ],
                    ],
                ],
            ],
        ];
        $data3 = [
            'with-route-and-nested-childsAlternative' => [
                'stack' => RouteStack::factory([
                    'router' => [
                        'Zend\Router\Http\Segment',
                        'route' => '/[:controller[/:action]]',
                        'defaults' => [
                            'controller' => 'fo-fo',
                            'action' => 'index'
                        ],
                    ],
                    'may_terminate' => true,
                    'routes'  => [
                        'wildcard' => ['router' => [
                            'Zend\Router\Http\Wildcard',
                            'key_value_delimiter' => '/',
                            'param_delimiter' => '/',
                        ]],
                    ],
                    'route_plugins' => $routePluginManager,
                ]),
                'data' => [
                    'simple-match' => [
                        '/',
                        null,
                        'expected' => [
                            'length' => null,
                            'name'   => null,
                            'params' => ['controller' => 'fo-fo', 'action' => 'index'],
                        ],
                    ],
                    'match-wildcard' => [
                        '/fo-fo/index/param1/value1',
                        null,
                        'expected' => [
                            'length' => null,
                            'name'   => 'wildcard',
                            'params' => ['controller' => 'fo-fo', 'action' => 'index', 'param1' => 'value1'],
                        ],
                    ],
                ],
            ],
        ];
        $data4 = [
            'chain' => [
                'stack' => RouteStack::factory([
                    'chains'  => [
                        [
                            'Zend\Router\Http\Segment',
                            'route'    => '/:controller',
                            'defaults' => ['controller' => 'foo'],
                        ],
                        [
                            'Zend\Router\Http\Segment',
                            'route'    => '/:bar',
                            'defaults' => ['bar' => 'bar'],
                        ],
                        [
                            'Zend\Router\Http\Wildcard',
                        ],
                    ],
                    'route_plugins' => $routePluginManager,
                ]),
                'data' => [
                    'simple-match' => [
                        '/foo/bar',
                        null,
                        'expected' => [
                            'length' => null,
                            'name'   => null,
                            'params' => ['controller' => 'foo', 'bar' => 'bar'],
                        ],
                    ],
                    'offset-skips-beginning' => [
                        '/baz/foo/bar',
                        4,
                        'expected' => [
                            'length' => 8, //!!!
                            'name'   => null,
                            'params' => ['controller' => 'foo', 'bar' => 'bar'],
                        ],
                    ],
                    'parameters-are-used-only-once' => [
                        '/foo/baz',
                        null,
                        'expected' => [
                            'length' => null,
                            'name'   => null,
                            'params' => ['controller' => 'foo', 'bar' => 'baz'],
                        ],
                    ],
                ],
            ],
            'chain-with-optional' => [
                'stack' => RouteStack::factory([
                    'chains'  => [
                        [
                            'Zend\Router\Http\Segment',
                            'route'    => '/:controller',
                            'defaults' => ['controller' => 'foo'],
                        ],
                        [
                            'Zend\Router\Http\Segment',
                            'route'    => '[/:bar]',
                            'defaults' => ['bar' => 'bar'],
                        ],
                    ],
                    'route_plugins' => $routePluginManager,
                ]),
                'data' => [
                    'parameter' => [
                        '/foo/baz',
                        null,
                        'expected' => [
                            'length' => null,
                            'name'   => null,
                            'params' => ['controller' => 'foo', 'bar' => 'baz'],
                        ],
                    ],
                    'parameter-empty' => [
                        '/foo',
                        null,
                        'expected' => [
                            'length' => null,
                            'name'   => null,
                            'params' => ['controller' => 'foo', 'bar' => 'bar'],
                        ],
                    ],
                ],
            ],
        ];
        $data5 = [
            'exceptions_1' => [
                'stack' => RouteStack::factory([
                    'route_plugins' => $routePluginManager,
                ]),
                'data' => [
                    'AssembleNonExistentRoute' => [
                        '/foo',
                        null,
                        'expected' => [
                            'assemble',
                            'length'  => null,
                            'name'    => 'foo',
                            'params'  => ['controller' => 'foo', 'bar' => 'bar'],
                            'exception' => [
                                'Zend\Router\Exception\RuntimeException',
                                'Route with name "foo" not exist',
                            ],
                        ],
                    ]
                ],
            ],
            'exceptions_2' => [
                'stack' => RouteStack::factory([
                    'routes' => [
                        'index' => ['router' => [
                            'Literal',
                            'route' => '/',
                        ]]
                    ],
                    'route_plugins' => $routePluginManager,
                ]),
                'data' => [
                    'AssembleNonExistentChildRoute' => [
                        '/foo',
                        null,
                        'expected' => [
                            'assemble',
                            'length'  => null,
                            'name'    => 'index/foo',
                            'params'  => [],
                            'exception' => [
                                'Zend\Router\Exception\RuntimeException',
                                'Route with name "foo" not exist',
                            ],
                        ],
                    ]
                ],
            ],
        ];
        $data6 = [
            'prototypes_1' => [
                'stack' => RouteStack::factory([
                    'prototypes' => [
                        'bar' => ['literal', 'route' => '/bar'],
                    ],
                    'routes' => [
                        'foo' => ['router' => 'bar'],
                    ],
                    'route_plugins' => $routePluginManager,
                ]),
                'data' => [
                    'PrototypeRoute' => [
                        '/bar',
                        null,
                        'expected' => [
                            'length'  => null,
                            'name'    => 'foo',
                            'params'  => [],
                        ],
                    ]
                ],
            ],
            'prototypes_2' => [
                'stack' => RouteStack::factory([
                    'prototypes' => [
                        'bar' => ['literal', 'route' => '/bar'],
                    ],
                    'routes' => [
                        'foo' => [
                            'router' => [
                                'type' => 'literal',
                                'route' => '/foo',
                            ],
                            'chains' => [
                                'bar' => 'bar',
                            ],
                        ],
                    ],
                    'route_plugins' => $routePluginManager,
                ]),
                'data' => [
                    'PrototypeInSubChain' => [
                        '/foo/bar',
                        null,
                        'expected' => [
                            'length'  => null,
                            'name'    => 'foo',
                            'params'  => [],
                        ],
                    ]
                ],
            ],
        ];
        $data = $this->prepareDataProvider($data1, $data2, $data3, $data4, $data5, $data6);
        if ($key = '') {
            return [$key => $data[$key]];
        } elseif ($key = 'chain-with-optional => parameter-empty') { //!!! TODO
            unset($data[$key]);
        }
        return $data;
    }

    protected function prepareDataProvider()
    {
        $result = [];
        foreach (func_get_args() as $arg) {
            foreach ($arg as $name => $test) {
                foreach ($test['data'] as $dataName => $dataData) {
                    if (substr($dataName, 0, 3) == '+++') {
                        return ["$name => $dataName" => array_merge([$test['stack']], $dataData)];
                    }
                    $result["$name => $dataName"] = array_merge([$test['stack']], $dataData);
                }
            }
        }
        return $result;
    }

    /**
     * @dataProvider routeProvider
     * @param        RouteStack $stack
     * @param        string     $path
     * @param        integer    $offset
     * @param        array      $expected
     */
    public function testMatching($stack, $path, $offset, array $expected = null)
    {
        if (isset($expected[0]) && $expected[0] != 'match') {
            return;
        }
        $request = new Request();
        $request->setUri('http://example.com' . $path);
        $match = $stack->match($request, $offset);

        if ($expected === null) {
            $this->assertNull($match);
            return;
        }

        $this->assertInstanceOf('Zend\Router\Http\RouteMatch', $match);

        if (array_key_exists('length', $expected)) {
            $this->assertEquals(
                $expected['length'] === null ? strlen($path) : $expected['length'],
                $match->getLength()
            );
        }
        if (array_key_exists('name', $expected)) {
            $this->assertEquals($expected['name'], $match->getMatchedRouteName());
        }
        if (array_key_exists('params', $expected)) {
            foreach ($expected['params'] as $key => $value) {
                $this->assertEquals($value, $match->getParam($key));
            }
        }
        return;
    }

    /**
     * @dataProvider routeProvider
     * @param        RouteStack $stack
     * @param        string     $path
     * @param        integer    $offset
     * @param        array      $expected
     */
    public function testAssembling($stack, $path, $offset, array $expected = null)
    {
        if (isset($expected[0]) && $expected[0] != 'assemble') {
            return;
        }
        if ($expected === null || $expected['params'] === null) {
            // Data which will not match are not tested for assembling.
            return;
        }

        if (isset($expected['exception'])) {
            if (count($expected['exception']) == 1) {
                $this->setExpectedException($expected['exception'][0]);
            } elseif (count($expected['exception']) == 2) {
                $this->setExpectedException($expected['exception'][0], $expected['exception'][1]);
            }
        }
        $result = $stack->assemble($expected['params'], ['name' => $expected['name']]);

        if (array_key_exists('assembled_params', $expected)) {
            $this->assertEquals(
                $expected['assembled_params'],
                $stack->getAssembledParams()
            );
        }
        if ($offset !== null) {
            $this->assertEquals($offset, strpos($path, $result, $offset));
        } else {
            $this->assertEquals($path, $result);
        }
    }

    public function testAssembleParams()
    {
        $stack = RouteStack::factory([
            'router' => [
                'Zend\Router\Http\Segment',
                'route' => '/:r1Key',
                'defaults' => [
                    'r1DefKey' => 'r1DefVal'
                ],
            ],
            'routes'  => [
                'route1' => [
                    'router' => [
                        'Zend\Router\Http\Segment',
                        'route' => '/:r2Key',
                        'defaults' => [
                            'r2DefKey' => 'r2DefVal'
                        ],
                    ]
                ],
            ],
            'chains'  => [
                [
                    'Zend\Router\Http\Segment',
                    'route' => '/:r3Key',
                    'defaults' => [
                        'r3DefKey' => 'r3DefVal'
                    ],
                ],
                [
                    'Zend\Router\Http\Segment',
                    'route' => '/:r4Key',
                    'defaults' => [
                        'r4DefKey' => 'r4DefVal'
                    ],
                ],
            ],
            'defaults' => [
                'r0DefKey' => 'r0DefVal'
            ],
            'may_terminate' => true,
        ]);
        $request = new Request();
        $request->getUri()->setPath('/foo/bar/baz/bat');
        $match = $stack->match($request, 0);

        $this->assertEquals(
            [
                'r0DefKey' => 'r0DefVal',
                'r1Key'    => 'foo',
                'r1DefKey' => 'r1DefVal',
                'r2Key'    => 'bat',
                'r2DefKey' => 'r2DefVal',
                'r3Key'    => 'bar',
                'r3DefKey' => 'r3DefVal',
                'r4Key'    => 'baz',
                'r4DefKey' => 'r4DefVal',
            ],
            $match->getParams()
        );

        $this->assertEquals(
            '/foo/bar/baz/bat',
            $stack->assemble([
                'r1Key'    => 'foo',
                'r2Key'    => 'bat',
                'r3Key'    => 'bar',
                'r4Key'    => 'baz',
            ], ['name' => 'route1'])
        );
        $this->assertEquals(
            [
                'r1Key',
                'r3Key',
                'r4Key',
                'r2Key',
            ],
            $stack->getAssembledParams()
        );
    }

    public function testSetAndGetRoute()
    {
        $stack = RouteStack::factory(['route_plugins' => $this->routePluginManager]);
        $this->assertNull($stack->getRouter());

        $literal = new Router\Http\Literal('/foo');
        $this->assertSame(
            $literal,
            $stack->setRouter($literal)->getRouter()
        );

        $this->assertNull($stack->setRouter(null)->getRouter());

        $this->assertInstanceOf(
            Router\Http\Wildcard::class,
            $stack->setRouter([Router\Http\Wildcard::class])->getRouter()
        );

        $this->assertInstanceOf(
            Router\Http\Wildcard::class,
            $stack->setRouter(new \ArrayObject([Router\Http\Wildcard::class]))->getRouter()
        );

        $this->setExpectedException('Zend\Router\Exception\InvalidArgumentException');
        $stack->setRouter(new Request());
    }

    public function testSetRouteAsArrayWithoutType()
    {
        $this->setExpectedException('Zend\Router\Exception\InvalidArgumentException', 'Missing "type" option');
        $stack = new RouteStack();
        $stack->setRouter(['router'=>'literal']);
        $stack->getRouter();
    }

    public function testSetGetChains()
    {
        $stack = RouteStack::factory([
            'route_plugins' => $this->routePluginManager,
        ]);

        $this->assertEquals(0, $stack->getChains()->count());
        $stack->setChains([
            [Router\Http\Wildcard::class],
            new \ArrayObject([Router\Http\Wildcard::class]),
            new Http\TestAsset\DummyRoute,
        ]);
        $this->assertEquals(3, $stack->getChains()->count());

        foreach ($stack->getChains() as $chain) {
            $this->assertNotInstanceOf(RouteStack::class, $chain);
            $this->assertInstanceOf(Router\RouteInterface::class, $chain);
        }

        $stack->setChains([]);
        $this->assertEquals(0, $stack->getChains()->count());

        $this->setExpectedException(
            'Zend\Router\Exception\InvalidArgumentException',
            'addChainRoutes expects an array or Traversable set of routes'
        );
        $stack = new RouteStack();
        $stack->setChains('foo');
    }

    public function testAddChains()
    {
        $stack = RouteStack::factory([
            'route_plugins' => $this->routePluginManager,
        ]);

        $this->assertEquals(0, $stack->getChains()->count());

        $stack->addChains([
            'chain1' => [Router\Http\Wildcard::class]
        ]);
        $this->assertEquals(1, $stack->getChains()->count());

        $stack->addChains(new \ArrayObject([
            'chain2' => ['Zend\Router\Http\Literal']
        ]));
        $this->assertEquals(2, $stack->getChains()->count());
    }

    public function testGetChainByName()
    {
        $stack = RouteStack::factory([
            'chains'  => [
                'route1' => [
                    Router\Http\Literal::class,
                    'route'    => '/foo',
                ],
                'route2' => [
                    Router\Http\Wildcard::class,
                ],
            ],
            'route_plugins' => $this->routePluginManager,
        ]);
        $this->assertInstanceOf(Router\Http\Literal::class, $stack->getChain('route1'));
        $this->assertInstanceOf(Router\Http\Wildcard::class, $stack->getChain('route2'));
    }

    public function testRemoveChains()
    {
        $routes = [
            'route1' => [Router\Http\Wildcard::class],
            'route2' => [Router\Http\Wildcard::class],
            'route3' => [Router\Http\Wildcard::class],
        ];
        $stack = RouteStack::factory(['route_plugins' => $this->routePluginManager])
                ->addChains($routes)
                ->removeChain('route2');
        unset($routes['route2']);
        $this->assertEquals(
            $routes,
            $stack->getChains()->toArray()
        );
    }

    public function testSetGetRoutes()
    {
        $stack = RouteStack::factory([
            'route_plugins' => $this->routePluginManager,
        ]);

        $this->assertEquals(0, $stack->getRoutes()->count());

        $stack->setRoutes([
            ['route' => 'Zend\Router\Http\Literal'],
            new \ArrayObject(['route' => 'Zend\Router\Http\Literal']),
            RouteStack::factory(['route' => 'Zend\Router\Http\Literal']),
            ['route' => new \ZendTest\Router\Http\TestAsset\DummyRoute],
        ]);
        $this->assertEquals(4, $stack->getRoutes()->count());

        foreach ($stack->getRoutes() as $k=>$v) {
            $this->assertInstanceOf(RouteStack::class, $v);
        }

        $stack->setRoutes([]);
        $this->assertEquals(0, $stack->getRoutes()->count());

        $this->setExpectedException(
            'Zend\Router\Exception\InvalidArgumentException',
            'addChildRoutes expects an array or Traversable set of routes'
        );
        $stack = new RouteStack();
        $stack->setRoutes('foo');
    }

    public function testAddRoutes()
    {
        $stack = RouteStack::factory([
            'route_plugins' => $this->routePluginManager,
        ]);

        $this->assertEquals(0, $stack->getRoutes()->count());

        $stack->addRoutes([
            'child1' => [Router\Http\Wildcard::class]
        ]);
        $this->assertEquals(1, $stack->getRoutes()->count());

        $stack->addRoutes(new \ArrayObject([
            'child2' => ['Zend\Router\Http\Literal']
        ]));
        $this->assertEquals(2, $stack->getRoutes()->count());
    }

    public function testHasRoutes()
    {
        $stack = RouteStack::factory([
            'route_plugins' => $this->routePluginManager,
        ]);
        $stack->addRoutes([
            'child1' => [Router\Http\Literal::class]
        ]);

        $this->assertTrue($stack->hasRoute('child1'));
        $this->assertFalse($stack->hasRoute('notFound'));
    }

    public function testRemoveRoutes()
    {
        $routes = [
            'route1' => [Router\Http\Literal::class],
            'route2' => [Router\Http\Literal::class],
            'route3' => [Router\Http\Literal::class],
        ];
        $stack = RouteStack::factory(['route_plugins' => $this->routePluginManager])
                ->addRoutes($routes)
                ->removeRoute('route2');
        unset($routes['route2']);
        $this->assertEquals(
            $routes,
            $stack->getRoutes()->toArray()
        );
    }

    public function testDefaultParamIsAddedToMatch()
    {
        $stack = RouteStack::factory([
            'routes' => [
                'foo' => ['router' => new Http\TestAsset\DummyRoute()]
            ],
            'defaults' => ['foo' => 'bar'],
            'route_plugins' => $this->routePluginManager
        ]);

        $this->assertEquals('bar', $stack->match(new Request())->getParam('foo'));
    }

    public function testDefaultParamDoesNotOverrideParam()
    {
        $stack = RouteStack::factory([
            'routes' => [
                'foo' => ['router' => new Http\TestAsset\DummyRouteWithParam()],
            ],
            'defaults' => ['foo' => 'baz'],
            'route_plugins' => $this->routePluginManager
        ]);

        $this->assertEquals('bar', $stack->match(new Request())->getParam('foo'));
        $this->assertEquals('bar', $stack->assemble(['foo' => 'bar'], ['name' => 'foo']));
    }

    public function testDefaultParamIsUsedForAssembling()
    {
        $stack = RouteStack::factory([
            'routes' => [
                'foo' => ['router' => new Http\TestAsset\DummyRouteWithParam()]
            ],
            'defaults' => ['foo' => 'bar'],
            'route_plugins' => $this->routePluginManager
        ]);

        $this->assertEquals('bar', $stack->assemble([], ['name' => 'foo']));
    }

    public function testSetRoutePluginManager()
    {
        $pluginManager = new RoutePluginManager(new ServiceManager());
        $stack  = new RouteStack();
        $stack->setRoutePluginManager($pluginManager);

        $this->assertSame($pluginManager, $stack->getRoutePluginManager());
    }
}
