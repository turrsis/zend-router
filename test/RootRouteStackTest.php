<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Router;

use PHPUnit_Framework_TestCase as TestCase;
use ArrayIterator;
use Zend\Http\Request as Request;
use Zend\Http\PhpEnvironment\Request as PhpRequest;
use Zend\Stdlib\Request as BaseRequest;
use Zend\Uri\Http as HttpUri;
use Zend\Router\RootRouteStack;
use Zend\Router\Http\Hostname;
use Zend\Router\RoutePluginManager;
use Zend\ServiceManager\ServiceManager;

class RootRouteStackTest extends TestCase
{
    /**
     * @var RoutePluginManager
     */
    protected $routePluginManager;

    /**
     * @var type RootRouteStack
     */
    protected $stack;

    public function setUp()
    {
        $this->routePluginManager = new RoutePluginManager(new ServiceManager());
        $this->stack = new RootRouteStack();
        $this->stack->setRoutePluginManager($this->routePluginManager);
    }

    public function QtestAddRouteRequiresHttpSpecificRoute()
    {
        $this->setExpectedException(
            'Zend\Router\Exception\InvalidArgumentException',
            'Route definition must be an array or Traversable object'
        );
        $this->stack->addRoute('foo', new \ZendTest\Router\TestAsset\DummyRoute());
    }

    public function QtestAddRouteViaStringRequiresHttpSpecificRoute()
    {
        $this->setExpectedException(
            'Zend\Router\Exception\RuntimeException',
            'Given route does not implement HTTP route interface'
        );
        $this->stack->addRoute('foo', [
            'type' => '\ZendTest\Router\TestAsset\DummyRoute'
        ]);
    }

    public function testAddRouteAcceptsTraversable()
    {
        $this->stack->addRoute('foo', new ArrayIterator([
            'type' => '\ZendTest\Router\Http\TestAsset\DummyRoute'
        ]));
    }

    public function testNoMatchWithoutUriMethod()
    {
        $request = new BaseRequest();

        $this->assertNull($this->stack->match($request));
    }

    public function testSetBaseUrlFromFirstMatch()
    {
        $request = new PhpRequest();
        $request->setBaseUrl('/foo');
        $this->stack->match($request);
        $this->assertEquals('/foo', $this->stack->getBaseUrl());

        $request = new PhpRequest();
        $request->setBaseUrl('/bar');
        $this->stack->match($request);
        $this->assertEquals('/foo', $this->stack->getBaseUrl());
    }

    public function testBaseUrlLengthIsPassedAsOffset()
    {
        $this->stack->setBaseUrl('/foo');
        $this->stack->addRoute('foo', ['router' => [
            'type' => '\ZendTest\Router\Http\TestAsset\DummyRoute'
        ]]);
        $this->assertEquals(4, $this->stack->match(new Request())->getParam('offset'));
    }

    public function testNoOffsetIsPassedWithoutBaseUrl()
    {
        $this->stack->addRoute('foo', ['router' => [
            'type' => '\ZendTest\Router\Http\TestAsset\DummyRoute'
        ]]);

        $this->assertEquals(null, $this->stack->match(new Request())->getParam('offset'));
    }

    public function testAssemble()
    {
        $this->stack->addRoute('foo', ['router' => new TestAsset\DummyRoute()]);
        $this->assertEquals('', $this->stack->assemble([], ['name' => 'foo']));
    }

    public function testAssembleCanonicalUriWithoutRequestUri()
    {
        $this->setExpectedException('Zend\Router\Exception\RuntimeException', 'Request URI has not been set');

        $this->stack->addRoute('foo', new TestAsset\DummyRoute());
        $this->assertEquals(
            'http://example.com:8080/',
            $this->stack->assemble([], ['name' => 'foo', 'force_canonical' => true])
        );
    }

    public function testAssembleCanonicalUriWithRequestUri()
    {
        $uri   = new HttpUri('http://example.com:8080/');
        $this->stack->setRequestUri($uri);

        $this->stack->addRoute('foo', ['router' => new TestAsset\DummyRoute()]);
        $this->assertEquals(
            'http://example.com:8080/',
            $this->stack->assemble([], ['name' => 'foo', 'force_canonical' => true])
        );
    }

    public function testAssembleCanonicalUriWithGivenUri()
    {
        $uri   = new HttpUri('http://example.com:8080/');

        $this->stack->addRoute('foo', ['router' => new TestAsset\DummyRoute()]);
        $this->assertEquals(
            'http://example.com:8080/',
            $this->stack->assemble([], ['name' => 'foo', 'uri' => $uri, 'force_canonical' => true])
        );
    }

    public function testAssembleCanonicalUriWithHostnameRoute()
    {
        $this->stack->addRoute('foo', ['router' => new Hostname('example.com')]);
        $uri   = new HttpUri();
        $uri->setScheme('http');

        $this->assertEquals('http://example.com/', $this->stack->assemble([], ['name' => 'foo', 'uri' => $uri]));
    }

    public function testAssembleCanonicalUriWithHostnameRouteWithoutScheme()
    {
        $this->setExpectedException('Zend\Router\Exception\RuntimeException', 'Request URI has not been set');

        $this->stack->addRoute('foo', ['router' => new Hostname('example.com')]);
        $uri   = new HttpUri();

        $this->assertEquals('http://example.com/', $this->stack->assemble([], ['name' => 'foo', 'uri' => $uri]));
    }

    public function testAssembleCanonicalUriWithHostnameRouteAndRequestUriWithoutScheme()
    {
        $uri   = new HttpUri();
        $uri->setScheme('http');
        $this->stack->setRequestUri($uri);
        $this->stack->addRoute('foo', ['router' => new Hostname('example.com')]);

        $this->assertEquals('http://example.com/', $this->stack->assemble([], ['name' => 'foo']));
    }

    public function testAssembleWithQueryParams()
    {
        $this->stack->addRoute('index', ['router' => [
            'Literal',
            'route' => '/',
        ]]);

        $this->assertEquals('/?foo=bar', $this->stack->assemble([], ['name' => 'index', 'query' => ['foo' => 'bar']]));
    }

    public function testAssembleWithEncodedPath()
    {
        $this->stack->addRoute('index', ['router' => [
            'Literal',
            'route' => '/this%2Fthat',
        ]]);

        $this->assertEquals('/this%2Fthat', $this->stack->assemble([], ['name' => 'index']));
    }

    public function testAssembleWithEncodedPathAndQueryParams()
    {
        $this->stack->addRoute('index', ['router' => [
            'Literal',
            'route' => '/this%2Fthat',
        ]]);

        $this->assertEquals(
            '/this%2Fthat?foo=bar',
            $this->stack->assemble([], ['name' => 'index', 'query' => ['foo' => 'bar'], 'normalize_path' => false])
        );
    }

    public function testAssembleWithScheme()
    {
        $uri   = new HttpUri();
        $uri->setScheme('http');
        $uri->setHost('example.com');
        $this->stack->setRequestUri($uri);
        $this->stack->addRoute('secure', [
            'router' => [
                'Scheme',
                'scheme' => 'https',
            ],
            'routes' => [
                'index' => ['router' => [
                    'Literal',
                    'route'    => '/',
                ]],
            ],
        ]);
        $this->assertEquals('https://example.com/', $this->stack->assemble([], ['name' => 'secure/index']));
    }

    public function testAssembleWithFragment()
    {
        $this->stack->addRoute('index', ['router' => [
            'Literal',
            'route' => '/',
        ]]);

        $this->assertEquals('/#foobar', $this->stack->assemble([], ['name' => 'index', 'fragment' => 'foobar']));
    }

    public function testAssembleWithoutNameOption()
    {
        $this->setExpectedException('Zend\Router\Exception\InvalidArgumentException', 'Missing "name" option');
        $this->stack->assemble();
    }

    public function testDefaultParamIsAddedToMatch()
    {
        $this->stack->setBaseUrl('/foo');
        $this->stack->addRoute('foo', ['router' => new TestAsset\DummyRoute()]);
        $this->stack->setDefault('foo', 'bar');

        $this->assertEquals('bar', $this->stack->match(new Request())->getParam('foo'));
    }

    public function testDefaultParamDoesNotOverrideParam()
    {
        $this->stack->setBaseUrl('/foo');
        $this->stack->addRoute('foo', ['router' => new TestAsset\DummyRouteWithParam()]);
        $this->stack->setDefault('foo', 'baz');

        $this->assertEquals('bar', $this->stack->match(new Request())->getParam('foo'));
    }

    public function testDefaultParamIsUsedForAssembling()
    {
        $this->stack->addRoute('foo', ['router' => new TestAsset\DummyRouteWithParam()]);
        $this->stack->setDefault('foo', 'bar');

        $this->assertEquals('bar', $this->stack->assemble([], ['name' => 'foo']));
    }

    public function testDefaultParamDoesNotOverrideParamForAssembling()
    {
        $this->stack->addRoute('foo', ['router' => new TestAsset\DummyRouteWithParam()]);
        $this->stack->setDefault('foo', 'baz');

        $this->assertEquals('bar', $this->stack->assemble(['foo' => 'bar'], ['name' => 'foo']));
    }

    public function testSetBaseUrl()
    {
        $this->assertEquals($this->stack, $this->stack->setBaseUrl('/foo/'));
        $this->assertEquals('/foo', $this->stack->getBaseUrl());
    }

    public function testSetRequestUri()
    {
        $uri   = new HttpUri();

        $this->assertSame($this->stack, $this->stack->setRequestUri($uri));
        $this->assertSame($uri, $this->stack->getRequestUri());
    }

    public function testChainRouteAssemblingWithChildrenAndSecureScheme()
    {
        $uri = new \Zend\Uri\Http();
        $uri->setHost('localhost');

        $this->stack->setRequestUri($uri);
        $this->stack->addRoute(
            'foo',
            [
                'router' => [
                    'type' => 'literal',
                    'route' => '/foo',
                ],
                'chains' => [
                    'scheme' => [
                        'scheme',
                        'scheme' => 'https',
                    ]
                ],
                'routes' => [
                    'baz' => ['router' => [
                        'literal',
                        'route' => '/baz',
                    ]]
                ]
            ]
        );
        $this->assertEquals('https://localhost/foo/baz', $this->stack->assemble([], ['name' => 'foo/baz']));
    }
}
