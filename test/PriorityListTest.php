<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Router;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Router\PriorityList;

class PriorityListTest extends TestCase
{
    public function testResolverForGet()
    {
        $list = new PriorityList(function ($data) {
            return new \ArrayObject($data);
        });
        $list->insert('foo', ['bar'=>'baz']);

        $this->assertEquals(
            new \ArrayObject(['bar'=>'baz']),
            $list->get('foo')
        );
        $this->assertSame($list->get('foo'), $list->get('foo'));
    }

    public function testResolverForCurrent()
    {
        $list = new PriorityList(function ($data) {
            return new \ArrayObject($data);
        });
        $list->insert('foo', ['bar'=>'baz']);

        $this->assertEquals(
            new \ArrayObject(['bar'=>'baz']),
            $list->current()
        );
        $this->assertSame($list->current(), $list->current());
    }
}
