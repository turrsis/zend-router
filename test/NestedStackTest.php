<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Router;

use Zend\Router\RouteStack;
use Zend\Router\NestedStack;
use Zend\Http\PhpEnvironment\Request;
use Zend\Router\Http\Wildcard as ContainerRouter;
use Zend\Router\RoutePluginManager;
use Zend\ServiceManager\ServiceManager;
use Zend\ServiceManager\Config;

class NestedStackTest extends \PHPUnit_Framework_TestCase
{
    protected $router = null;
    protected $request = null;

    /**
     * @var RoutePluginManager
     */
    protected $routePluginManager;

    public function setUp()
    {
        $this->router = NestedStack::factory([
            'router' => new ContainerRouter('/', '/', ['bar'=>'baz']),
            'anch'             => '~',
            'main_container'   => 'k0',
            'defaults'   => [
                'controller' => 'ccc',
                'action'     => 'aaa',
            ],
        ]);
        $this->request = new Request();
        $this->routePluginManager = new RoutePluginManager(new ServiceManager());
    }

    protected function match($path, $pathOffset = 0)
    {
        $this->request->getUri()->setPath($path);
        $match = $this->router->match($this->request, $pathOffset);
        return $match;
    }

    public function testMatch_EmptyPath()
    {
        $match       = $this->match('');

        $this->assertMatchedNode($match, [
            'params'    => [],
            'options'   => [
                'anch' => '~',
                'main_container' => 'k0',
            ],
            'childrens' => [
                'k0' => [
                    'child_keys' => [],
                    'options'    => [
                        'name'      => 'k0',
                        'key'       => 'k0',
                        'path'  => '/',
                        'prefix'=> '',
                        'is_client' => false,
                    ],
                    'params' => [
                        'controller' => 'ccc',
                        'action'     => 'aaa',
                        'bar'        => 'baz',
                    ],
                    'childrens' => [],
                ],
            ],
        ]);
    }

    public function testMatch_WithOffset()
    {
        $path        = 'xxx/~k0/a0/b0';
        $pathOffset  = 4;
        $match       = $this->match($path, $pathOffset);

        $this->assertMatchedNode($match, [
            'length'    => strlen($path) - $pathOffset,
            'params'    => [],
            'options'   => [
                'anch' => '~',
                'main_container' => 'k0',
            ],
            'childrens' => [
                'k0' => [
                    'child_keys' => [],
                    'options'    => [
                        'name'      => 'k0',
                        'key'       => 'k0',
                        'path'  => '/a0/b0',
                        'prefix'=> '',
                        'is_client' => true,
                    ],
                    'params' => [
                        'bar' => 'baz',
                        'a0'  => 'b0',
                        'controller' => 'ccc',
                        'action'     => 'aaa',
                    ],
                    'childrens' => [],
                ],
            ],
        ]);
    }

    public function testMatch_WithoutContainerName()
    {
        $this->router = NestedStack::factory([
            'main_container'   => 'k0',
            'router' => [
                \Zend\Router\Http\Segment::class,
                'route'    => '/:controller[/:action]',
                'defaults' => [
                    'controller' => 'index',
                    'action'     => 'index',
                ],
            ],
        ]);

        $this->assertMatchedNode($this->match('/a0/b0'), [
            'length'    => 6,
            'params'    => [],
            'options'   => [
                'anch' => '~',
                'main_container' => 'k0',
            ],
            'childrens' => [
                'k0' => [
                    'child_keys' => [],
                    'options'    => [
                        'name'      => 'k0',
                        'key'       => 'k0',
                        'path'      => '/a0/b0',
                        'prefix'    => '',
                        'is_client' => true,
                    ],
                    'params' => [
                        'controller' => 'a0',
                        'action'     => 'b0',
                    ],
                    'childrens' => [],
                ],
            ],
        ]);

        $this->assertMatchedNode($this->match('/a0'), [
            'length'    => 3,
            'params'    => [],
            'options'   => [
                'anch' => '~',
                'main_container' => 'k0',
            ],
            'childrens' => [
                'k0' => [
                    'child_keys' => [],
                    'options'    => [
                        'name'      => 'k0',
                        'key'       => 'k0',
                        'path'      => '/a0',
                        'prefix'    => '',
                        'is_client' => true,
                    ],
                    'params' => [
                        'controller' => 'a0',
                        'action'     => 'index',
                    ],
                    'childrens' => [],
                ],
            ],
        ]);
    }

    public function testMatch_StartWithSecondLevel()
    {
        $path        = '~~k00/a00/b00';
        $match       = $this->match($path);

        $this->assertMatchedNode($match, [
            'length'    => strlen($path),
            'params'    => [],
            'options'   => [
                'anch' => '~',
                'main_container' => 'k0',
            ],
            'childrens' => [
                'k0' => [
                    'options'    => [
                        'name'      => 'k0',
                        'key'       => 'k0',
                        'path'  => '/',
                        'prefix'=> '',
                        'is_client' => true,
                    ],
                    'params' => [
                        'controller' => 'ccc',
                        'action'     => 'aaa',
                        'bar' => 'baz',
                    ],
                    'childrens' => [
                        'k00' => [
                            'params' => [
                                'bar' => 'baz',
                                'a00'  => 'b00',

                                'controller' => 'ccc', //!!! раньше этого небыло
                                'action'     => 'aaa', //!!!
                            ],
                            'options' => [
                                'key'       => 'k0\k00',
                                'name'      => 'k00',
                                'path'  => '/a00/b00',
                                'prefix'=> '/~~k00',
                                'is_client' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function testMatch_StartWithFirstLevelAndNoDefault()
    {
        $path       = '~k1/a1/b1';
        $match      = $this->match($path);

        $this->assertMatchedNode($match, [
            'length'    => strlen($path),
            'params'    => [],
            'options'   => [
                'anch' => '~',
                'main_container' => 'k0',
            ],
            'childrens' => [
                'k0' => [
                    'options'    => [
                        'name'      => 'k0',
                        'key'       => 'k0',
                        'path'  => '/',
                        'prefix'=> '',
                        'is_client' => false,
                    ],
                    'params' => [
                        'controller' => 'ccc',
                        'action'     => 'aaa',
                        'bar' => 'baz'
                    ],
                    'childrens' => [],
                ],
                'k1' => [
                    'options'    => [
                        'name'      => 'k1',
                        'key'       => 'k1',
                        'path'  => '/a1/b1',
                        'prefix'=> '/~k1',
                        'is_client' => true,
                    ],
                    'params' => [
                        'bar' => 'baz',
                        'a1'  => 'b1',

                        'controller' => 'ccc', //!!! раньше этого небыло
                        'action'     => 'aaa', //!!!
                    ],
                    'childrens' => [],
                ],
            ],
        ]);
    }

    public function testMatch()
    {
        $path        = '~k0/a0/b0/~~k00/a00/b00/~~~k000/a000/b000/~k1/a1/b1';
        $match       = $this->match($path);

        $this->assertMatchedNode($match, [
            'length'    => strlen($path),
            'params'    => [],
            'options'   => [
                'anch' => '~',
                'main_container' => 'k0',
            ],
            'childrens' => [
                'k0' => [
                    'options'    => [
                        'name'      => 'k0',
                        'key'       => 'k0',
                        'path'  => '/a0/b0',
                        'prefix'=> '',
                        'is_client' => true,
                    ],
                    'params' => [
                        'bar' => 'baz',
                        'a0'  => 'b0',
                        'controller' => 'ccc',
                        'action'     => 'aaa',
                    ],
                    'childrens' => [
                        'k00' => [
                            'options'    => [
                                'name'      => 'k00',
                                'key'       => 'k0\k00',
                                'path'  => '/a00/b00',
                                'prefix'=> '/~~k00',
                                'is_client' => true,
                            ],
                            'params' => [
                                'bar' => 'baz',
                                'a00' => 'b00',

                                'controller' => 'ccc', //!!! раньше этого небыло
                                'action'     => 'aaa', //!!!
                            ],
                            'childrens' => [
                                'k000' => [
                                    'options'    => [
                                        'name'      => 'k000',
                                        'key'       => 'k0\k00\k000',
                                        'path'  => '/a000/b000',
                                        'prefix'=> '/~~~k000',
                                        'is_client' => true,
                                    ],
                                    'params' => [
                                        'bar'  => 'baz',
                                        'a000' => 'b000',

                                        'controller' => 'ccc', //!!! раньше этого небыло
                                        'action'     => 'aaa', //!!!
                                    ],
                                    'childrens' => [],
                                ],
                            ],
                        ],
                    ],
                ],
                'k1' => [
                    'options'    => [
                        'name'      => 'k1',
                        'key'       => 'k1',
                        'path'  => '/a1/b1',
                        'prefix'=> '/~k1',
                        'is_client' => true,
                    ],
                    'params' => [
                        'bar' => 'baz',
                        'a1'  => 'b1',

                        'controller' => 'ccc', //!!! раньше этого небыло
                        'action'     => 'aaa', //!!!
                    ],
                    'childrens' => [],
                ],
            ],
        ]);
    }

    public function testMatch_WithParentRouter()
    {
        $this->router = RouteStack::factory([
            'routes' => [
                'cms' => [
                    'router' => [
                        \Zend\Router\Http\Literal::class,
                        'route'    => '/foo',
                        'defaults' => [
                            'bar' => 'baz',
                        ],
                    ],
                    'routes'      => [
                        'nested' => [
                            NestedStack::class,
                            'router' => new \Zend\Router\Http\Wildcard('/', '/'),
                            'main_container'   => 'c0',
                            'defaults'    => [
                                'nested_defaults_param' => 'v2',
                            ],
                        ],
                    ],
                ],
            ],
            'route_plugins' => $this->routePluginManager,
        ]);

        $path1 = '/foo';
        $path2 = '/~c0/a0/b0';
        $match = $this->match($path1 . $path2);
        $this->assertMatchedNode($match, [
            'length'    => strlen($path1 . $path2),
            'params'    => [
                'bar' => 'baz'
            ],
            'options'   => [
                'anch' => '~',
                'main_container' => 'c0',
            ],
            'matchedRouteName' => 'cms/nested',
            'childrens' => [
                'c0' => [
                    'length'  => strlen($path2),
                    'options' => [
                        'name'      => 'c0',
                        'key'       => 'c0',
                        'path'      => '/a0/b0',
                        'prefix'    => '',
                        'is_client' => true,
                    ],
                    'params' => [
                        'a0' => 'b0',
                        'nested_defaults_param' => 'v2',
                    ],
                    'childrens' => [],
                ],
            ],
        ]);
    }

    public function testMatch_WithParentAndChildsRouter()
    {
        $this->router = RouteStack::factory([
            'routes' => [
                'cms' => [
                    'router' => [
                        \Zend\Router\Http\Literal::class,
                        'route'    => '/foo',
                        'defaults' => [
                            'bar' => 'baz',
                        ],
                    ],
                    'routes'      => [
                        'nested' => [
                            NestedStack::class,
                            'main_container'   => 'k0',
                            'routes'    => [
                                'child0' => [
                                    'router' => [
                                        \Zend\Router\Http\Segment::class,
                                        'route'    => '/child0[/:action]',
                                        'defaults' => [
                                            'controller' => 'c0',
                                        ],
                                    ],
                                ],
                                'child1' => [
                                    'router' => [
                                        \Zend\Router\Http\Segment::class,
                                        'route'    => '/child1[/:action]',
                                        'defaults' => [
                                            'controller' => 'c1',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'route_plugins' => $this->routePluginManager,
        ]);

        $path0 = '/~k0/child0/a0/';
        $path1 = '~k1/child1/a1';
        $path = '/foo' . $path0 . $path1;
        $match = $this->match($path);

        $this->assertMatchedNode($match, [
            'length'    => strlen($path),
            'params'    => [
                'bar' => 'baz'
            ],
            'options'   => [
                'anch' => '~',
                'main_container' => 'k0',
            ],
            'matchedRouteName' => 'cms/nested',
            'childrens' => [
                'k0' => [
                    'length'    => strlen($path0),
                    'options'    => [
                        'name'      => 'k0',
                        'key'       => 'k0',
                        'path'      => '/child0/a0',
                        'prefix'    => '',
                        'is_client' => true,
                    ],
                    'params' => [
                        'controller' => 'c0',
                        'action'     => 'a0',
                    ],
                    'childrens' => [],
                    'matchedRouteName' => 'child0',
                ],
                'k1' => [
                    'length'    => strlen($path1),
                    'options'    => [
                        'name'      => 'k1',
                        'key'       => 'k1',
                        'path'      => '/child1/a1',
                        'prefix'    => '/~k1',
                        'is_client' => true,
                    ],
                    'params' => [
                        'controller' => 'c1',
                        'action'     => 'a1',
                    ],
                    'childrens' => [],
                    'matchedRouteName' => 'child1',
                ],
            ],
        ]);
    }

    public function testMatch_WithChildsRoutes()
    {
        $this->router = NestedStack::factory([
            'anch'             => '~',
            'main_container'   => 'k0',
            'routes'    => [
                'child0' => [
                    'router' => [
                        \Zend\Router\Http\Segment::class,
                        'route'    => '/child0[/:action]',
                        'defaults' => [
                            'controller' => 'c0',
                        ],
                    ],
                ],
                'child1' => [
                    'router' => [
                        \Zend\Router\Http\Segment::class,
                        'route'    => '/child1[/:action]',
                        'defaults' => [
                            'controller' => 'c1',
                        ],
                    ],
                ],
            ],
        ]);
        $match = $this->match('~k0/child0/a0/~k1/child1/a1');
        
        $this->assertNotNull($match);
        $this->assertMatchedNode($match, [
            'params'    => [],
            'childrens' => [
                'k0' => [
                    'options'    => [
                        'name'      => 'k0',
                        'key'       => 'k0',
                        'path'      => '/child0/a0',
                        'prefix'    => '',
                        'is_client' => true,
                    ],
                    'params' => [
                        'controller' => 'c0',
                        'action'     => 'a0',
                    ],
                    'childrens' => [],
                    'matchedRouteName' => 'child0',
                ],
                'k1' => [
                    'options'    => [
                        'name'      => 'k1',
                        'key'       => 'k1',
                        'path'      => '/child1/a1',
                        'prefix'    => '/~k1',
                        'is_client' => true,
                    ],
                    'params' => [
                        'controller' => 'c1',
                        'action'     => 'a1',
                    ],
                    'childrens' => [],
                    'matchedRouteName' => 'child1',
                ],
            ],
        ]);
    }

    public function testMatch_CanNotMatchNode()
    {
        $this->router = NestedStack::factory([
            'anch'             => '~',
            'main_container'   => 'k0',
            'router'    => [
                \Zend\Router\Http\Literal::class,
                'route'    => '/foo',
                'defaults' => [
                    'p' => 'v',
                ],
            ],
        ]);

        $this->assertMatchedNode('~k0/xxx/~~k01/foo/~~~k011/foo/~k1/foo', [
            'params'    => [],
            'childrens' => [
                'k0' => false,
                'k1' => [
                    'length'  => 7,
                    'options' => ['key' => 'k1'],
                    'params'  => ['p'   => 'v'],
                    'childrens' => [],
                ],
            ],
        ]);
        $this->assertMatchedNode('~k0/foo/~~k01/xxx/', [
            'childrens' => [
                'k0' => [
                    'length'    => 8,
                    'options'   => ['key' => 'k0'],
                    'params'    => ['p'   => 'v'],
                    'childrens' => [
                        'k01' => false,
                    ],
                ],
            ],
        ]);
        $this->assertMatchedNode('~k0/foo/~k1/xxx/~k2/foo/', [
            'childrens' => [
                'k0' => [
                    'length'    => 8,
                    'params'    => ['p' => 'v'],
                    'childrens' => [],
                ],
                'k1' => false,
                'k2' => [
                    'length'    => 8,
                    'params'    => ['p' => 'v'],
                    'childrens' => [],
                ],
            ],
        ]);
        $this->assertMatchedNode('~k0/foo/~k1/xxx/~~k11/zzz/~k2/foo/', [
            'childrens' => [
                'k0' => [
                    'length'    => 8,
                    'params'    => ['p' => 'v'],
                    'childrens' => [],
                ],
                'k1' => false,
                'k2' => [
                    'length'    => 8,
                    'params'    => ['p' => 'v'],
                    'childrens' => [],
                ],
            ],
        ]);
        $this->assertMatchedNode('~k0/foo/~k1/foo/~~k11/foo/~k2/xxx/~k3/foo', [
            'childrens' => [
                'k0' => [
                    'length'    => 8,
                    'params'    => ['p' => 'v'],
                    'childrens' => [],
                ],
                'k1' => [
                    'length'    => 8,
                    'params'    => ['p' => 'v'],
                    'childrens' => [
                        'k11' => [
                            'length'    => 10,
                            'params'    => ['p' => 'v'],
                            'childrens' => [],
                        ],
                    ],
                ],
                'k2' => false,
                'k3' => [
                    'length'    => 7,
                    'params'    => ['p' => 'v'],
                    'childrens' => [],
                ],
            ],
        ]);
    }

    public function testAssemble()
    {
        $defaultMatch = $this->match('~k0/a0/~~k01/a01/~~k02/a02/~~~k021/a021/~~k03/a03');

        $defaultMatch->getChildren('k0')->getChildren('k01')->addChildren('k011', [
            'options' => [
                'path'      => 'a011',
            ],
        ]);

        $defaultMatch->getChildren('k0')->getChildren('k01')->getChildren('k011')->addChildren('k0111', [
            'options' => [
                'path'      => 'a0111',
            ],
        ]);

        $this->assertEquals( // without containers
            '',
            $this->router->assemble([])
        );

        $this->assertEquals( // with empty containers
            '/a0/~~k01/a01/~~k02/a02/~~~k021/a021/~~k03/a03',
            $this->router->assemble([
                'containers' => [],
            ])
        );
        $this->assertEquals( // Clear container path
            '/a0/~~k02/a02/~~~k021/a021/~~k03/a03',
            $this->router->assemble(['containers' => [
                'k0\k01' => null
            ]])
        );
        $this->assertEquals( // Change root container path
            '/x0',
            $this->router->assemble(['containers' => [
                'k0' => 'x0',
            ]])
        );
        $this->assertEquals( // Change container path
            '/a0/~~k01/x01/~~k02/a02/~~~k021/a021/~~k03/a03',
            $this->router->assemble(['containers' => [
                'k0\k01' => 'x01'
            ]])
        );
        $this->assertEquals( // Change path for two nested containers
            '/x0/~~k01/x01/~~k02/a02/~~~k021/a021/~~k03/a03',
            $this->router->assemble(['containers' => [
                'k0'     => 'x0',
                'k0\k01' => 'x01',
            ]])
        );
        $this->assertEquals( // Change not Client container
            '/a0/~~k01/a01/~~~k011/x011/~~k02/a02/~~~k021/a021/~~k03/a03',
            $this->router->assemble(['containers' => [
                'k0\k01\k011' => 'x011',
            ]])
        );
        $this->assertEquals( // Change not Client container
            '/a0/~~k01/a01/~~~k011/a011/~~~~k0111/x0111/~~k02/a02/~~~k021/a021/~~k03/a03',
            $this->router->assemble(['containers' => [
                'k0\k01\k011\k0111' => 'x0111',
            ]])
        );
        $this->assertEquals( // Change path for two nested not Clients containers
            '/a0/~~k01/a01/~~~k011/x011/~~~~k0111/x0111/~~k02/a02/~~~k021/a021/~~k03/a03',
            $this->router->assemble(['containers' => [
                'k0\k01\k011'       => 'x011',
                'k0\k01\k011\k0111' => 'x0111',
            ]])
        );
        $this->assertEquals( // with not exist container
            '/{=errPathInfo:{"key":"xx;","containers":{"xx":"yy"}}=}',
            $this->router->assemble(['containers' => [
                'xx'     => 'yy'
            ]])
        );
        $this->assertEquals( // not merge root params
            '/bar/baz/p1/v1',
            $this->router->assemble([
                'p0' => 'v0',
                'containers' => [
                    'k0'     => ['p1'=>'v1'],
                ],
            ])
        );
        $this->assertEquals( // (not)Merge root params if container is string
            '/xxx',
            $this->router->assemble([
                'root_p0' => 'zzz',
                'containers' => [
                    'k0' => 'xxx'
                ],
            ])
        );

        $useAllParams = new \ReflectionProperty($this->router, 'useAllParams');
        $useAllParams->setAccessible(true);
        $useAllParams->setValue($this->router, true);
        $this->assertEquals( // Merge root params
            '/bar/baz/p0/v0/p1/v1',
            $this->router->assemble([
                'p0' => 'v0',
                'containers' => [
                    'k0'     => ['p1'=>'v1'],
                ],
            ])
        );
    }

    public function testAssembleFromCache()
    {
        $this->match('~k0/a0');
        $this->assertEquals(
            '/foo',
            $this->router->assemble(['containers' => [
                'k0'     => 'foo',
            ]])
        );

        $cache = new \ReflectionProperty($this->router, '_cache');
        $cache->setAccessible(true);
        $cache->setValue($this->router, [
            'ok' => [
                'k0;' => [
                    (object)[
                        'key' => 'k0',
                        'left' => 'bar',
                        'right' => 'baz',
                    ]
                ]
            ],
        ]);
        

        $this->assertEquals(
            '/bar/foo/baz',
            $this->router->assemble(['containers' => [
                'k0'     => 'foo',
            ]])
        );
    }

    public function testAssemble_ResolveContainer()
    {
        $defaultMatch = $this->match('~k0/a0/~~k01/a01/~~~k011/a011/~~k02/a02/~~~k021/a021/~~k03/a03/~k1/a1');
        $this->router->setCurrent($defaultMatch->getChildren('k0')->getChildren('k02'));

        $this->assertEquals( // Resolve {default}
            '/[default]/~k1/a1',
            $this->router->assemble(['containers' => [
                '{default}' => '[default]'
            ]])
        );
        $this->assertEquals( // Resolve {current}
            '/a0/~~k01/a01/~~~k011/a011/~~k02/[current]/~~k03/a03/~k1/a1',
            $this->router->assemble(['containers' => [
                '{current}' => '[current]'
            ]])
        );
        $this->assertEquals( // Resolve {parent}
            '/[parent]/~k1/a1',
            $this->router->assemble(['containers' => [
                '{parent}' => '[parent]'
            ]])
        );
        $this->assertEquals( // Resolve {prev_sibling}
            '/a0/~~k01/[prev_sibling]/~~k02/a02/~~~k021/a021/~~k03/a03/~k1/a1',
            $this->router->assemble(['containers' => [
                '{prev_sibling}' => '[prev_sibling]'
            ]])
        );
        $this->assertEquals( // Resolve {next_sibling}
            '/a0/~~k01/a01/~~~k011/a011/~~k02/a02/~~~k021/a021/~~k03/[next_sibling]/~k1/a1',
            $this->router->assemble(['containers' => [
                '{next_sibling}' => '[next_sibling]'
            ]])
        );
    }

    public function testAssemble_WithNotClientChilds()
    {
        $this->router = NestedStack::factory([
            'anch'             => '~',
            'main_container'   => 'k0',
            'routes'    => [
                'home' => [
                    'router' => [
                        \Zend\Router\Http\Literal::class,
                        'route'    => '/',
                        'defaults' => [
                            'controller' => 'CCC1',
                            'action'     => 'AAA1',
                        ],
                    ],
                ],
                'application' => [
                    'router' => [
                        \Zend\Router\Http\Segment::class,
                        'route'    => '/application[/:action]',
                        'defaults' => [
                            'controller' => 'CCC2',
                            'action'     => 'AAA2',
                        ],
                    ],
                ],
            ],
        ]);

        $rootMatch = $this->match('~k0/a0/~k1/a1');
        $rootMatch->getChildren('k0')->setOption('is_client', false);
        $rootMatch->getChildren('k1')->setOption('is_client', false);

        $assembled1 = $this->router->assemble([
                'containers' => [
                    'k0' => 'bar0',
                    'k1' => 'bar1',
                ],
            ], ['name' => 'nested']);
        $this->assertEquals('/bar0/~k1/bar1', $assembled1);

        $assembled2 = $this->router->assemble([
                'containers' => [
                    'k0' => 'bar0',
                    'k1' => 'bar1',
                ],
            ], ['name' => 'nested']);
        $this->assertEquals('/bar0/~k1/bar1', $assembled2);
    }

    protected function assertMatchedNode($node, $expected)
    {
        if (is_string($node)) {
            $node = $this->match($node);
        }

        $this->assertNotNull($node);

        if ($expected === false) {
            $this->assertFalse($node);
            return;
        }

        if (isset($expected['length'])) {
            $this->assertEquals($node->getLength(), $expected['length']);
        }
        if (array_key_exists('matchedRouteName', $expected)) {
            $this->assertEquals($expected['matchedRouteName'], $node->getMatchedRouteName());
        }
        if (isset($expected['params'])) {
            $this->assertArraySubset($expected['params'], $node->getParams());
        }
        if (isset($expected['options'])) {
            $this->assertArraySubset($expected['options'], $node->getOptions());
        }
        if (isset($expected['parent'])) {
            $this->assertSame($expected['parent'], $node->getParent());
        }
        if (isset($expected['child_keys'])) {
            $this->assertSame($expected['child_keys'], array_keys($node->getChildrens()));
        }
        if (isset($expected['childrens'])) {
            if ($expected['childrens'] == []) {
                $this->assertSame([], $node->getChildrens());
            } else {
                foreach ($expected['childrens'] as $childName => $child) {
                    $this->assertMatchedNode($node->getChildren($childName), $child);
                }
            }
        }
    }
}
