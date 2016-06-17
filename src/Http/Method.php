<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Router\Http;

use Traversable;
use Zend\Router\Exception;
use Zend\Stdlib\ArrayUtils;
use Zend\Stdlib\RequestInterface as Request;

/**
 * Method route.
 */
class Method extends AbstractRoute
{
    /**
     * Verb to match.
     *
     * @var string
     */
    protected $verb;

    /**
     * Create a new method route.
     *
     * @param  string $verb
     * @param  array  $defaults
     */
    public function __construct($verb, array $defaults = [])
    {
        $this->verb     = $verb;
        $this->defaults = $defaults;
    }

    /**
     * factory(): defined by RouteInterface interface.
     *
     * @see    \Zend\Router\RouteInterface::factory()
     * @param  array|Traversable $options
     * @return Method
     * @throws Exception\InvalidArgumentException
     */
    public static function factory($options = [])
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (!is_array($options)) {
            throw new Exception\InvalidArgumentException(sprintf(
                '%s expects an array or Traversable set of options',
                __METHOD__
            ));
        }

        if (!isset($options['verb'])) {
            throw new Exception\InvalidArgumentException('Missing "verb" in options array');
        }

        if (!isset($options['defaults'])) {
            $options['defaults'] = [];
        }

        return new static($options['verb'], $options['defaults']);
    }

    /**
     * match(): defined by RouteInterface interface.
     *
     * @see    \Zend\Router\RouteInterface::match()
     * @param  Request $request
     * @return RouteMatch|null
     */
    public function match(Request $request)
    {
        if (!method_exists($request, 'getMethod')) {
            return;
        }

        $requestVerb = strtoupper($request->getMethod());
        $matchVerbs  = explode(',', strtoupper($this->verb));
        $matchVerbs  = array_map('trim', $matchVerbs);

        if (in_array($requestVerb, $matchVerbs)) {
            return new RouteMatch($this->defaults);
        }

        return;
    }

    /**
     * assemble(): Defined by RouteInterface interface.
     *
     * @see    \Zend\Router\RouteInterface::assemble()
     * @param  array $params
     * @param  array $options
     * @return mixed
     */
    public function assemble(array $params = [], array $options = [])
    {
        // The request method does not contribute to the path, thus nothing is returned.
        return '';
    }
}
