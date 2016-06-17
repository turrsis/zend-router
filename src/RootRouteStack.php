<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Router;

use Zend\Router\RouteStack;
use Zend\Router\Exception;
use Zend\Stdlib\RequestInterface as Request;
use Zend\Uri\Http as HttpUri;

class RootRouteStack extends RouteStack
{
    /**
     * Base URL.
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Request URI.
     *
     * @var HttpUri
     */
    protected $requestUri;

    /**
     * match(): defined by \Zend\Router\RouteInterface
     *
     * @see    \Zend\Router\RouteInterface::match()
     * @param  Request      $request
     * @param  integer|null $pathOffset
     * @param  array        $options
     * @return RouteMatch|null
     */
    public function match(Request $request, $pathOffset = null, array $options = [])
    {
        if (!method_exists($request, 'getUri')) {
            return;
        }

        if ($this->baseUrl === null && method_exists($request, 'getBaseUrl')) {
            $this->setBaseUrl($request->getBaseUrl());
        }

        $uri           = $request->getUri();
        $baseUrlLength = strlen($this->baseUrl) ?: null;

        if ($pathOffset !== null) {
            $baseUrlLength += $pathOffset;
        }

        if ($this->requestUri === null) {
            $this->setRequestUri($uri);
        }

        return parent::match($request, $baseUrlLength, $options);
    }

    /**
     * assemble(): defined by \Zend\Router\RouteInterface interface.
     *
     * @see    \Zend\Router\RouteInterface::assemble()
     * @param  array $params
     * @param  array $options
     * @return mixed
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     */
    public function assemble(array $params = [], array $options = [])
    {
        if (!isset($options['name'])) {
            throw new Exception\InvalidArgumentException('Missing "name" option');
        }
        if (isset($options['only_return_path']) && $options['only_return_path']) {
            return $this->baseUrl . parent::assemble(array_merge($this->defaults, $params), $options);
        }

        if (!isset($options['uri'])) {
            $uri = new HttpUri();

            if (isset($options['force_canonical']) && $options['force_canonical']) {
                if ($this->requestUri === null) {
                    throw new Exception\RuntimeException('Request URI has not been set');
                }

                $uri->setScheme($this->requestUri->getScheme())
                    ->setHost($this->requestUri->getHost())
                    ->setPort($this->requestUri->getPort());
            }

            $options['uri'] = $uri;
        } else {
            $uri = $options['uri'];
        }

        $path = $this->baseUrl . parent::assemble(array_merge($this->defaults, $params), $options);

        if (isset($options['query'])) {
            $uri->setQuery($options['query']);
        }

        if (isset($options['fragment'])) {
            $uri->setFragment($options['fragment']);
        }

        if ((isset($options['force_canonical'])
            && $options['force_canonical'])
            || $uri->getHost() !== null
            || $uri->getScheme() !== null
        ) {
            if (($uri->getHost() === null || $uri->getScheme() === null) && $this->requestUri === null) {
                throw new Exception\RuntimeException('Request URI has not been set');
            }

            if ($uri->getHost() === null) {
                $uri->setHost($this->requestUri->getHost());
            }

            if ($uri->getScheme() === null) {
                $uri->setScheme($this->requestUri->getScheme());
            }

            $uri->setPath($path);

            if (!isset($options['normalize_path']) || $options['normalize_path']) {
                $uri->normalize();
            }

            return $uri->toString();
        } elseif (!$uri->isAbsolute() && $uri->isValidRelative()) {
            $uri->setPath($path);

            if (!isset($options['normalize_path']) || $options['normalize_path']) {
                $uri->normalize();
            }

            return $uri->toString();
        }

        return $path;
    }

    /**
     * Set the base URL.
     *
     * @param  string $baseUrl
     * @return self
     */
    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        return $this;
    }

    /**
     * Get the base URL.
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Set the request URI.
     *
     * @param  HttpUri $uri
     * @return TreeRouteStack
     */
    public function setRequestUri(HttpUri $uri)
    {
        $this->requestUri = $uri;
        return $this;
    }

    /**
     * Get the request URI.
     *
     * @return HttpUri
     */
    public function getRequestUri()
    {
        return $this->requestUri;
    }
}
