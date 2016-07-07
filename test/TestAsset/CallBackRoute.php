<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Router\TestAsset;

use Zend\Router\RouteInterface;
use Zend\Stdlib\RequestInterface;

class CallBackRoute implements RouteInterface
{
    protected $match;

    protected $assemble;

    public function match(RequestInterface $request, $pathOffset = null, array $options = array())
    {
        return $this->match
            ? call_user_func($this->match, $request, $pathOffset, $options)
            : null;
    }

    public function assemble(array $params = array(), array $options = array())
    {
        return $this->assemble
            ? call_user_func($this->assemble, $params, $options)
            : null;
    }

    public function getAssembledParams()
    {
        return array();
    }

    public static function factory($options = array())
    {
        $instance = new self();
        if (isset($options['match_CallBack'])) {
            $instance->match = $options['match_CallBack'];
        }
        if (isset($options['assemble_CallBack'])) {
            $instance->assemble = $options['assemble_CallBack'];
        }
        return $instance;
    }
}
