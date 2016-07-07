<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Router\Http;

use Zend\Router\RouteMatch as BaseRouteMatch;
use Zend\Stdlib\ArrayUtils;
/**
 * Part route match.
 */
class RouteMatch extends BaseRouteMatch
{
    /**
     * Length of the matched path.
     *
     * @var int
     */
    protected $length;

    protected $parent;

    protected $childrens = [];

    protected $depth = 0;

    protected $options = [];

    /**
     * Create a part RouteMatch with given parameters and length.
     *
     * @param  array   $params
     * @param  int $length
     */
    public function __construct(array $params, $length = 0)
    {
        parent::__construct($params);

        $this->length = $length;
    }

    /**
     * setMatchedRouteName(): defined by BaseRouteMatch.
     *
     * @see    BaseRouteMatch::setMatchedRouteName()
     * @param  string $name
     * @return RouteMatch
     */
    public function setMatchedRouteName($name)
    {
        if ($this->matchedRouteName === null) {
            $this->matchedRouteName = $name;
        } else {
            $this->matchedRouteName = $name . '/' . $this->matchedRouteName;
        }

        return $this;
    }

    /**
     * Merge parameters from another match.
     *
     * @param  RouteMatch $match
     * @return RouteMatch
     */
    public function merge($match, array $options = [])
    {
        if (is_array($match)) {
            $this->params  = ArrayUtils::merge($this->params, $match);
        } elseif ($match instanceof RouteMatch) {
            $this->params           = array_merge($this->params, $match->getParams());
            $this->length           += $match->getLength();
            $this->matchedRouteName = $match->getMatchedRouteName();

            $this->options          = array_merge($this->options, $match->getOptions());

            $skipChild = isset($options['is_internal']) ? $options['is_internal'] : null;
            foreach ($match->getChildrens() as $name => $child) {
                if ($skipChild === $child) {
                    continue;
                }
                if (isset($this->childrens[$name])) {
                    $this->childrens[$name]->merge($child, ['is_internal' => true]);
                } else {
                    $this->childrens[$name] = $child;
                }
            }
        } else {
            throw new \Exception('$match should be array or RouteMatch');
        }

        if (isset($options['length'])) {
            $this->length = $options['length'];
        }

        if (!isset($options['is_internal']) && $match instanceof RouteMatch && $match->parent) {
            if (!$this->parent) {
                $this->parent = $match->parent;
                foreach ($this->parent->childrens as &$thisMatch) {
                    if ($thisMatch == $match) {
                        $thisMatch = $this;
                        break;
                    }
                }
            } else {
                $this->parent->merge($match->parent, [
                    'is_internal' => $match
                ]);
            }
        }
        return $this;
    }

    /**
     * Get the matched path length.
     *
     * @return int
     */
    public function getLength()
    {
        return $this->length;
    }

    /**
     *
     * @param string $name
     * @param array|RouteMatch $children
     * @return self
     * @throws \Exception
     */
    public function addChildren($name, $children)
    {
        if ($children === null || $children === false) {
            $this->childrens[$name] = false;
            return $this;
        }
        if (is_array($children)) {
            $opts = $children;
            $children = new RouteMatch(isset($opts['params']) ? $opts['params'] : []);
            if (isset($opts['options'])) {
                $children->setOptions($opts['options']);
            }
        } elseif (!$children instanceof RouteMatch) {
            throw new \Exception('$child parameter must be array or Zend\Mvc\Router\Http\RouteMatch instance');
        }
        $this->childrens[$name] = $children;
        $children->parent = $this;
        $children->depth = $this->depth + 1;

        if ($children->getOption('key') !== null) {
            return $this;
        }

        $children->setOption('name', $name);

        $key = $name;
        if ($children->getParent()) {
            $key = $children->getParent()->getOption('key') . '\\' . $key;
        }
        $children->setOption('key', trim($key, '\\'));

        $root = $children->parent;
        while ($root->parent) {
            $root = $root->parent;
        }

        if ($children->getOption('name') == $root->getOption('main_container')) {
            $children->setOption('prefix', '');
        } else {
            $anch = str_repeat($root->getOption('anch'), $children->depth);
            $children->setOption('prefix', '/' . $anch . $children->getOption('name'));
        }

        if ($children->getOption('is_client') == null) {
            $children->setOption('is_client', false);
        }

        return $this;
    }

    /**
     *
     * @param string $name
     * @return RouteMatch
     */
    public function getChildren($name)
    {
        return isset($this->childrens[$name])
            ? $this->childrens[$name]
            : null;
    }

    /**
     *
     * @return array
     */
    public function getChildrens()
    {
        return $this->childrens;
    }

    /**
     *
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function setOption($name, $value)
    {
        $this->options[$name] = $value;
        return $this;
    }
    /**
     *
     * @param string $name
     * @return mixed
     */
    public function getOption($name)
    {
        return isset($this->options[$name])
            ? $this->options[$name]
            : null;
    }

    /**
     *
     * @param array $options
     * @return self
     */
    public function setOptions(array $options)
    {
        foreach ($options as $k => $v) {
            $this->options[$k] = $v;
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     *
     * @param array $params
     * @return self
     */
    public function setParams(array $params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * @return self
     */
    public function getParent()
    {
        return $this->parent;
    }

    public function getDepth()
    {
        return $this->depth;
    }
}
