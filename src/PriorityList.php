<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Router;

use Zend\Stdlib\PriorityList as StdlibPriorityList;

/**
 * Priority list
 */
class PriorityList extends StdlibPriorityList
{
    protected $resolver;
    protected $isLIFO = -1;

    public function __construct($resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Get a item.
     *
     * @param  string $name
     * @return RouteInterface
     */
    public function get($name)
    {
        if (!isset($this->items[$name])) {
            return;
        }
        if (isset($this->items[$name]['resolved'])) {
            return $this->items[$name]['data'];
        }

        $data = call_user_func($this->resolver, $this->items[$name]['data']);
        $this->set($name, $data);
        $this->items[$name]['resolved'] = true;
        return $data;
    }

    public function set($name, $value)
    {
        if (!isset($this->items[$name])) {
            throw new Exception\RuntimeException("item $name not found");
        }

        $this->items[$name]['data'] = $value;

        return $this;
    }

    public function current()
    {
        $this->sorted || $this->sort();
        $item = current($this->items);
        if (isset($item['resolved'])) {
            return $item['data'];
        }
        $data = call_user_func($this->resolver, $item['data']);
        $this->set($this->key(), $data);
        $this->items[$this->key()]['resolved'] = true;
        return $data;
    }

    public function has($name)
    {
        return isset($this->items[$name]);
    }
}
