<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Zend\Router;

use Interop\Container\ContainerInterface;
use Zend\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\Exception\InvalidServiceException;

/**
 * Plugin manager implementation for routes
 *
 * Enforces that routes retrieved are instances of RouteInterface. It overrides
 * configure() to map invokables to the component-specific
 * RouteInvokableFactory.
 *
 * The manager is marked to not share by default, in order to allow multiple
 * route instances of the same type.
 */
class RoutePluginManager extends AbstractPluginManager
{
    /**
     * Only RouteInterface instances are valid
     *
     * @var string
     */
    protected $instanceOf = RouteInterface::class;

    /**
     * Do not share instances. (v3)
     *
     * @var bool
     */
    protected $shareByDefault = false;

    /**
     * Do not share instances. (v2)
     *
     * @var bool
     */
    protected $sharedByDefault = false;

    /**
     * @var RoutePrototypesFactory
     */
    protected $prototypesFactory;

    /**
     * Constructor
     *
     * Ensure that the instance is seeded with the RouteInvokableFactory as an
     * abstract factory.
     *
     * @param ContainerInterface|\Zend\ServiceManager\ConfigInterface $configOrContainerInstance
     * @param array $v3config
     */
    public function __construct($configOrContainerInstance, array $v3config = [])
    {
        $this->addAbstractFactory(RouteInvokableFactory::class);
        $this->prototypesFactory = new RoutePrototypesFactory($this);

        $v3config = array_merge_recursive([
            'aliases' => [
                'chain'    => Http\Chain::class,
                'Chain'    => Http\Chain::class,
                'hostname' => Http\Hostname::class,
                'Hostname' => Http\Hostname::class,
                'hostName' => Http\Hostname::class,
                'HostName' => Http\Hostname::class,
                'literal'  => Http\Literal::class,
                'Literal'  => Http\Literal::class,
                'method'   => Http\Method::class,
                'Method'   => Http\Method::class,
                'part'     => Http\Part::class,
                'Part'     => Http\Part::class,
                'regex'    => Http\Regex::class,
                'Regex'    => Http\Regex::class,
                'scheme'   => Http\Scheme::class,
                'Scheme'   => Http\Scheme::class,
                'segment'  => Http\Segment::class,
                'Segment'  => Http\Segment::class,
                'wildcard' => Http\Wildcard::class,
                'Wildcard' => Http\Wildcard::class,
                'wildCard' => Http\Wildcard::class,
                'WildCard' => Http\Wildcard::class,
                'NestedStack' => NestedStack::class,
            ],
            'factories' => [
                Http\Chain::class    => RouteInvokableFactory::class,
                Http\Hostname::class => RouteInvokableFactory::class,
                Http\Literal::class  => RouteInvokableFactory::class,
                Http\Method::class   => RouteInvokableFactory::class,
                Http\Part::class     => RouteInvokableFactory::class,
                Http\Regex::class    => RouteInvokableFactory::class,
                Http\Scheme::class   => RouteInvokableFactory::class,
                Http\Segment::class  => RouteInvokableFactory::class,
                Http\Wildcard::class => RouteInvokableFactory::class,
                NestedStack::class => NestedStackFactory::class,
                // v2 normalized names

                'zendmvcrouterhttpchain'    => RouteInvokableFactory::class,
                'zendmvcrouterhttphostname' => RouteInvokableFactory::class,
                'zendmvcrouterhttpliteral'  => RouteInvokableFactory::class,
                'zendmvcrouterhttpmethod'   => RouteInvokableFactory::class,
                'zendmvcrouterhttppart'     => RouteInvokableFactory::class,
                'zendmvcrouterhttpregex'    => RouteInvokableFactory::class,
                'zendmvcrouterhttpscheme'   => RouteInvokableFactory::class,
                'zendmvcrouterhttpsegment'  => RouteInvokableFactory::class,
                'zendmvcrouterhttpwildcard' => RouteInvokableFactory::class,
            ],
        ], $v3config);

        parent::__construct($configOrContainerInstance, $v3config);
    }

    public function setPrototype($name, $service)
    {
        $this->prototypesFactory->setPrototype($name, $service);
        $this->setShared($name, true);
        $this->setFactory($name, $this->prototypesFactory);
    }

    /**
     * Validate a route plugin. (v2)
     *
     * @param object $plugin
     * @throws InvalidServiceException
     */
    public function validate($plugin)
    {
        if (! $plugin instanceof $this->instanceOf) {
            throw new InvalidServiceException(sprintf(
                'Plugin of type %s is invalid; must implement %s',
                (is_object($plugin) ? get_class($plugin) : gettype($plugin)),
                RouteInterface::class
            ));
        }
    }

    /**
     * Validate a route plugin. (v2)
     *
     * @param object $plugin
     * @throws Exception\RuntimeException
     */
    public function validatePlugin($plugin)
    {
        try {
            $this->validate($plugin);
        } catch (InvalidServiceException $e) {
            throw new Exception\RuntimeException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Pre-process configuration. (v3)
     *
     * Checks for invokables, and, if found, maps them to the
     * component-specific RouteInvokableFactory; removes the invokables entry
     * before passing to the parent.
     *
     * @param array $config
     * @return void
     */
    public function configure(array $config)
    {
        if (isset($config['invokables']) && ! empty($config['invokables'])) {
            $aliases   = $this->createAliasesForInvokables($config['invokables']);
            $factories = $this->createFactoriesForInvokables($config['invokables']);

            if (! empty($aliases)) {
                $config['aliases'] = isset($config['aliases'])
                    ? array_merge($config['aliases'], $aliases)
                    : $aliases;
            }

            $config['factories'] = isset($config['factories'])
                ? array_merge($config['factories'], $factories)
                : $factories;

            unset($config['invokables']);
        }
        if (isset($config['prototypes'])) {
            foreach($config['prototypes'] as $name => $prototype) {
                $this->setPrototype($name, $prototype);
            }
            unset($config['prototypes']);
        }

        parent::configure($config);
    }

     /**
     * Create aliases for invokable classes.
     *
     * If an invokable service name does not match the class it maps to, this
     * creates an alias to the class (which will later be mapped as an
     * invokable factory).
     *
     * @param array $invokables
     * @return array
     */
    protected function createAliasesForInvokables(array $invokables)
    {
        $aliases = [];
        foreach ($invokables as $name => $class) {
            if ($name === $class) {
                continue;
            }
            $aliases[$name] = $class;
        }
        return $aliases;
    }

    /**
     * Create invokable factories for invokable classes.
     *
     * If an invokable service name does not match the class it maps to, this
     * creates an invokable factory entry for the class name; otherwise, it
     * creates an invokable factory for the entry name.
     *
     * @param array $invokables
     * @return array
     */
    protected function createFactoriesForInvokables(array $invokables)
    {
        $factories = [];
        foreach ($invokables as $name => $class) {
            if ($name === $class) {
                $factories[$name] = RouteInvokableFactory::class;
                continue;
            }

            $factories[$class] = RouteInvokableFactory::class;
        }
        return $factories;
    }
}
