<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Router\Http;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Router\Http\RouteMatch;

class RouteMatchTest extends TestCase
{
    public function testParamsAreStored()
    {
        $match = new RouteMatch(['foo' => 'bar']);

        $this->assertEquals(['foo' => 'bar'], $match->getParams());
    }

    public function testLengthIsStored()
    {
        $match = new RouteMatch([], 10);

        $this->assertEquals(10, $match->getLength());
    }

    public function testLengthIsMerged()
    {
        $match = new RouteMatch([], 10);
        $match->merge(new RouteMatch([], 5));

        $this->assertEquals(15, $match->getLength());
    }

    public function testMatchedRouteNameIsSet()
    {
        $match = new RouteMatch([]);
        $match->setMatchedRouteName('foo');

        $this->assertEquals('foo', $match->getMatchedRouteName());
    }

    public function testMatchedRouteNameIsPrependedWhenAlreadySet()
    {
        $match = new RouteMatch([]);
        $match->setMatchedRouteName('foo');
        $match->setMatchedRouteName('bar');

        $this->assertEquals('bar/foo', $match->getMatchedRouteName());
    }

    public function testMatchedRouteNameIsOverriddenOnMerge()
    {
        $match = new RouteMatch([]);
        $match->setMatchedRouteName('foo');

        $subMatch = new RouteMatch([]);
        $subMatch->setMatchedRouteName('bar');

        $match->merge($subMatch);

        $this->assertEquals('bar', $match->getMatchedRouteName());
    }

    public function testMergeWithRemoveParams()
    {
        $match = new RouteMatch([
            'p1' => 'v1',
            'p2' => 'v2',
            'p3' => 'v3',
        ]);
        $match->merge(['p2'=> new \Zend\Stdlib\ArrayUtils\MergeRemoveKey]);

        $this->assertEquals(
            [
                'p1' => 'v1',
                'p3' => 'v3',
            ],
            $match->getParams()
        );
    }

    public function testMergeWithParents()
    {
        // Есть оба родителя
        $p1 = new RouteMatch(['p1' => 'p1_v']);
        $p1->addChildren('c1', new RouteMatch([
            'x1' => 'y1',
            'x2' => 'y2',
        ]));

        $p2 = new RouteMatch(['p2' => 'p2_v']);
        $p2->addChildren('c1', new RouteMatch([
            'x1' => 'y11',
        ]));
        $p2->addChildren('c2', new RouteMatch([
            'x3' => 'y3',
        ]));

        $p1c1 = $p1->getChildren('c1');

        $p1c1->merge($p2->getChildren('c1')/*, array('matched_route_name'=>'XXXXX')*/);

        $this->assertSame($p1c1, $p1->getChildren('c1'));
        $this->assertEquals(
            [
                'params' => [
                    'p1' => 'p1_v',
                    'p2' => 'p2_v',
                ],
                'c1' => [
                    //'route_name' => 'XXXXX',
                    'params' => [
                        'x1' => 'y11',
                        'x2' => 'y2',
                    ],
                ],
                'c2' => [
                    'x3' => 'y3',
                ],
            ],
            [
                'params' => $p1->getParams(),
                'c1'     => [
                    //'route_name' => $p1->getChildren('c1')->getMatchedRouteName(),
                    'params' => $p1->getChildren('c1')->getParams(),
                ],
                'c2'     => $p1->getChildren('c2')->getParams(),
            ]
        );
        //======================================================================
        // Один родитель справа
        $c1 = new RouteMatch([
            'x1' => 'y1',
            'x2' => 'y2'
        ]);

        $p2 = new RouteMatch(['p2' => 'p2_v']);
        $p2->addChildren('c1', new RouteMatch([
            'x1' => 'y11',
        ]));
        $p2->addChildren('c2', new RouteMatch([
            'x3' => 'y3',
        ]));

        $c1->merge($p2->getChildren('c1'));
        $p1 = $c1->getParent();

        $this->assertEquals($c1, $p1->getChildren('c1'));
        $this->assertEquals(
            [
                'params' => [
                    'p2' => 'p2_v'
                ],
                'c1' => [
                    'x1' => 'y11',
                    'x2' => 'y2',
                ],
                'c2' => [
                    'x3' => 'y3',
                ],
            ],
            [
                'params' => $p1->getParams(),
                'c1'     => $p1->getChildren('c1')->getParams(),
                'c2'     => $p1->getChildren('c2')->getParams(),
            ]
        );
        //======================================================================
        // Один родитель слева
        $p1 = new RouteMatch(['p1' => 'p1_v']);
        $p1->addChildren('c1', new RouteMatch([
            'x1' => 'y1',
            'x2' => 'y2',
        ]));
        $p1->addChildren('c2', new RouteMatch([
            'x3' => 'y3',
        ]));

        $c2 = new RouteMatch(['x1' => 'y11']);

        $p1c1 = $p1->getChildren('c1');
        $p1c1->merge($c2);
        $this->assertEquals(
            [
                'params' => [
                    'p1' => 'p1_v'
                ],
                'c1' => [
                    'x1' => 'y11',
                    'x2' => 'y2',
                ],
                'c2' => [
                    'x3' => 'y3'
                ],
            ],
            [
                'params' => $p1->getParams(),
                'c1'     => $p1->getChildren('c1')->getParams(),
                'c2'     => $p1->getChildren('c2')->getParams(),
            ]
        );
    }
}
