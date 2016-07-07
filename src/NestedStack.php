<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Router;

use Zend\Stdlib\RequestInterface;
use Zend\Router\Http\RouteMatch as HttpRouteMatch;
use Zend\ServiceManager\ServiceLocatorInterface;

class NestedStack extends RouteStack
{
    /**
     * @var HttpRouteMatch */
    protected $routeMatch = null;

    /**
     * @var HttpRouteMatch */
    protected $currentRoute;

    protected $anch = '~';

    protected $mainContainer = 'content';

    protected $useAllParams = false;

    protected $_cache   = [
        'ok'    => [],
        'err'   => [],
    ];
    protected $_errTemplate     = ['{=errPathInfo:', '%s', '=}'];

    public static function factory($route = [], RouteStackInterface $context = null)
    {
        $options = [];
        if (isset($route['anch'])) {
            $options['anch'] = $route['anch'];
            unset($route['anch']);
        }
        if (isset($route['main_container'])) {
            $options['main_container'] = $route['main_container'];
            unset($route['main_container']);
        }

        $instance = parent::factory($route, $context);
        if (isset($options['anch'])) {
            $instance->anch = $options['anch'];
        }
        if (isset($options['main_container'])) {
            $instance->mainContainer = $options['main_container'];
        }
        if (isset($options['use_all_params'])) {
            $instance->useAllParams = (bool)$options['use_all_params'];
        }
        return $instance;
    }

    public function assemble(array $params = [], array $options = [])
    {
        $this->assembledParams = [];
        if (!$params) {
            return '';
        }

        $params = $this->assembleReduce($params);
        $assembled = $this->assembleFromCache($params['key'], $params['containers']);
        if ($assembled === null) {
            $assembled = $this->assembleInternal($params['key'], $params['containers'], $this->routeMatch);
        }
        if ($assembled) {
            $this->assembledParams = ['containers'];
        }
        return $assembled;
    }

    protected function assembleInternal($containersKey, array $params, HttpRouteMatch $routeMatch)
    {
        /** === Reset ===================================*/
        /**/    $childs = $routeMatch->getChildrens();
        /**/    if (!$childs) {
        /**/        return '';
        /**/    }
        /**/    $current = reset($childs);
        /**/    $parents = [$childs];
        /** ============================================*/

        $funcBuildNodePath = function ($node, $path = null) {
            $path = $path ?: $node->getOption('path');
            $path = $path
                    ? '/' . trim($path, '/')
                    : '';
            return $node->getOption('prefix') . $path;
        };
        $res            = '';
        $cash           = [];
        $cashPath       = '';
        $processedCount = 0;
        $lockSefLevel   = null;

        while ($current) {
            $nodeKey   = $current->getOption('key');
            $nodeDepth = count($parents);
            if ($lockSefLevel >= $nodeDepth) {
                $lockSefLevel = null;
            }

            $pathCurrent = null;
            $pathTmp     = '';
            if (array_key_exists($nodeKey, $params)) {
                $pathCurrent = $params[$nodeKey] != null ? $params[$nodeKey] : false;
                $processedCount++;
            }

            $isClient = ($lockSefLevel !== null && $nodeDepth > $lockSefLevel)
                         ? false
                         : $current->getOption('is_client');
            if ($isClient === true) {
                if ($pathCurrent !== null) {
                    if ($pathCurrent !== false) {
                        $res .= $funcBuildNodePath($current, $pathCurrent);
                    }
                    $cash[]    = (object)[
                        'key'   => $nodeKey,
                        'left'  => rtrim($cashPath, '/') . '/' . ltrim($current->getOption('prefix'), '/'),
                        'right' => ''
                    ];
                    $cashPath  = '';
                    // Lock isClient for all childs, because path was change
                    if ($lockSefLevel === null || $lockSefLevel <= $nodeDepth) {
                        $lockSefLevel = $nodeDepth;
                    }
                } else {
                    $cashPath .= $funcBuildNodePath($current);
                    $res      .= $funcBuildNodePath($current);
                }
            } elseif ($pathCurrent !== null) {
                if ($pathCurrent !== false) {
                    $nodeParent = $current->getParent();
                    while ($nodeParent != null && $nodeParent->getOption('is_client') == false) {
                        if (!isset($params[$nodeParent->getOption('key')])) {
                            $pathTmp = $funcBuildNodePath($nodeParent) . $pathTmp;
                        }
                        $nodeParent = $nodeParent->getParent();
                    }
                }
                $cash[]   = (object)[
                    'key'   => $nodeKey,
                    'left'  => rtrim($cashPath, '/') . ($pathTmp != null ? '/'.trim($pathTmp, '/').'/' : '/') . ltrim($current->getOption('prefix'), '/'),
                    'right' => ''
                ];
                $cashPath = '';
                // Add to result
                $res       .= $pathTmp . ($pathCurrent !== false ? $funcBuildNodePath($current, $pathCurrent) : '');
                // Lock isClient for all childs, because path was change
                if ($lockSefLevel === null || $lockSefLevel <= $nodeDepth) {
                    $lockSefLevel = $nodeDepth;
                }
            }

            // === Next ========================================================
            if ($childs = $current->getChildrens()) {
                $parents[] = $childs;
                $current   = current($childs);
                continue;
            }
            $current = false;
            while ($parents) {
                $current = next($parents[count($parents) - 1]);
                if ($current) {
                    break;
                } else {
                    array_pop($parents);
                }
            }
        }

        if ($processedCount != count($params)) {
            $this->_cache['err'][$containersKey] = true;
            return '/' . sprintf(implode('', $this->_errTemplate), json_encode([
                'key'        => $containersKey,
                'containers' => $params,
            ]));
        }
        if (count($cash) != 0) {
            end($cash)->right = $cashPath;
        } else {
            $cash[] = (object)[
                'key'   => null,
                'left'  => '',
                'right' => $cashPath,
            ];
        }
        $this->_cache['ok'][$containersKey] = $cash;

        return $res;
    }

    protected function assembleFromCache($key, $containers)
    {
        if (isset($this->_cache['err'][$key])) {
            return '/' . sprintf(implode('', $this->_errTemplate), json_encode([
                'key'        => $key,
                'containers' => $containers,
            ]));
        }
        if (isset($this->_cache['ok'][$key])) {
            $res            = '';
            $reduceCallBack = function ($carry, $item) {
                if ($item != null && $item != '/') {
                    $carry .= '/' . trim($item, '/');
                }
                return $carry;
            };
            foreach ($this->_cache['ok'][$key] as $cacheElement) {
                $cacheKey  = $cacheElement->key;
                $cacheLeft = $cacheElement->left;
                $cntrPath  = '';
                if (array_key_exists($cacheKey, $containers)) {
                    if (($cntrPath = $containers[$cacheKey]) == null) {
                        $cacheLeft = rtrim(rtrim($cacheLeft, $cacheKey), $this->anch);
                    }
                    unset($containers[$cacheKey]);
                }

                $res = (string)array_reduce(
                    [$res, $cacheLeft, $cntrPath, $cacheElement->right],
                    $reduceCallBack
                );
            }
            return $res;
        }
        return;
    }

    protected function assembleReduce($params)
    {
        if (isset($params['key']) && isset($params['containers'])) {
            return $params;
        }

        if (isset($params['containers'])) {
            $containers = $params['containers'];
            unset($params['containers']);
        } else {
            $containers = [];
        }
        if ($this->useAllParams && $params) {
            if (isset($containers[$this->mainContainer])) {
                if (is_array($containers[$this->mainContainer])) {
                    $containers[$this->mainContainer] = array_merge($params, $containers[$this->mainContainer]);
                }
            } else {
                $containers[$this->mainContainer] = $params;
            }
        }

        $key      = '';
        $resolved = [];
        foreach ($containers as $keyPath => $container) {
            if (is_array($container)) {
                if (isset($container['route'])) {
                    $routeName = $container['route'];
                    unset($container['route']);
                } else {
                    $routeName = 'default';
                    $routeName = null;
                }
                $container = parent::assemble($container, [
                    'name'              => $routeName,
                    'only_return_path'  => true,
                    'force_canonical'   => false,
                ]);
            }
            $keyPath = $this->resolveContainer($keyPath);
            $key    .= $keyPath . ';';
            $resolved[$keyPath] = $container;
        }
        return [
            'key'        => $key,
            'containers' => $resolved,
        ];
    }

    public function getAssembledParams()
    {
        return $this->assembledParams;
    }

    public function match(RequestInterface $request, $pathOffset = 0, $options = [])
    {
        $uri       = $request->getUri();
        $fullPath  = $uri->getPath() ? substr($uri->getPath(), $pathOffset) : '';
        $path      = ltrim($fullPath, '/');
        $trimCount = strlen($fullPath) - strlen($path);
        $result    = (new HttpRouteMatch([], strlen($fullPath)))->setOptions([
            'anch'           => $this->anch,
            'main_container' => $this->mainContainer,
        ]);

        $res = preg_match_all(
            '/(?:(?P<anch>~+)(?P<name>[A-Za-z0-9_-]+)){0,}(?P<path>[=\%\.\/A-Za-z0-9_-]{1,})/',
            $path,
            $matches,
            PREG_SET_ORDER
        );

        if ($matches && $matches[0]['anch'] == '') {
            // Default container without name
            $matches[0]['path'] = '/' . ltrim(trim($matches[0]['name'], '/') . '/' . ltrim($matches[0]['path'], '/'), '/');
            $matches[0]['anch'] = $this->anch;
            $matches[0]['name'] = $this->mainContainer;
        }

        if (!$matches
                || $matches[0]['anch'] == $this->anch . $this->anch
                || $matches[0]['name'] != $this->mainContainer) {
            // Add default container
            array_splice($matches, 0, 0, [[
                'name'      => $this->mainContainer,
                'anch'      => $this->anch,
                'path'      => '/',
                'is_client' => ($matches && $matches[0]['anch'] == $this->anch . $this->anch),
            ]]);
        }

        $parents   = [];
        $prevDepth = 0;
        $lockDepth = PHP_INT_MAX;
        foreach ($matches as $i => $match) {
            $matchDepth = strlen($match['anch']) - 1;
            if ($matchDepth > $lockDepth) {
                continue;
            } else {
                $lockDepth = PHP_INT_MAX;
            }
            $uri->setPath('/' . trim($match['path'], '/'));
            $routeMatch = parent::match($request);
            if ($routeMatch) {
                $routeMatch->merge([], [
                    'length' => ($i == 0 ? $trimCount : 0) + strlen($match['anch'] . $match['name'] . $match['path']),
                ]);
                $routeMatch->setOptions([
                    'name'      => $match['name'],
                    'prefix'    => '/' . $match['anch'] . $match['name'],
                    'path'      => '/' . trim($match['path'], '/'),
                    'is_client' => isset($match['is_client']) ? $match['is_client'] : true,
                ]);
            } else {
                $lockDepth = $matchDepth;
            }
            if ($parents && ($sliceLength = $matchDepth - $prevDepth) <= 0) {
                $parents = array_slice($parents, 0, $sliceLength - 1, false);
            }
            $parent = $parents ? end($parents) : $result;
            $parent->addChildren(
                $match['name'],
                $routeMatch
            );
            if ($routeMatch) {
                $parents[] = $routeMatch;
            }
            $prevDepth = $matchDepth;
        }

        $uri->setPath($path);
        if (!$this->routeMatch) {
            $this->routeMatch = $result;
        }
        return $result;
    }

    public function resolveContainer($key)
    {
        $current = $this->getCurrent();
        return preg_replace_callback(
            "/{(\S*)}/",
            function ($key) use ($current) {
                if ($key[1] != 'default' && !$current) {
                    return '/errNotCurrent';
                }
                $resNode = null;
                switch ($key[1]) {
                    case 'default'       :
                        $resNode = $this->routeMatch->getChildren($this->mainContainer);
                        break;
                    case 'current'       :
                        $resNode = $current;
                        break;
                    case 'parent'        :
                        $resNode = $current->getParent();
                        break;
                    case 'prev_sibling'  :
                        $currentName = $current->getOption('name');
                        $siblings = $current->getParent()->getChildrens();
                        foreach ($siblings as $k => $v) {
                            if ($k == $currentName) {
                                break;
                            }
                            $resNode = $v;
                        }
                        break;
                    case 'next_sibling'  :
                        $currentName = $current->getOption('name');
                        $siblings    = $current->getParent()->getChildrens();
                        $curr        = reset($siblings);
                        while (!$resNode || $curr) {
                            if ($currentName == key($siblings)) {
                                $resNode = next($siblings);
                                break;
                            }
                            $curr = next($siblings);
                        }
                        break;
                }
                if (!$resNode) {
                    return "{err-$key[1]}";
                }
                return $resNode->getOption('key');
            },
            $key
        );
    }

    /**
     * @return HttpRouteMatch
     */
    public function getCurrent()
    {
        return $this->currentRoute;
    }

    /**
     * @param type $routeMatch
     * @return self
     */
    public function setCurrent($routeMatch)
    {
        $this->currentRoute = $routeMatch;
        return $this;
    }
}
