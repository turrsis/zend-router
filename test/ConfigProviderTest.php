<?php
/**
 * @link      http://github.com/zendframework/zend-router for the canonical source repository
 * @copyright Copyright (c) 2005-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace ZendTest\Router;

use PHPUnit_Framework_TestCase as TestCase;
use Zend\Router\ConfigProvider;
use Zend\ServiceManager\ServiceManager;

class ConfigProviderTest extends TestCase
{
    /**
     * @var ConfigProvider
     */
    protected $provider;

    public function setUp()
    {
        $this->provider = new ConfigProvider();
    }

    public function testResolverForGet()
    {
        $config = $this->provider->getDependencyConfig();
        $sm = new ServiceManager($config);
        $this->assertSame(
            $sm->get('HttpRouter'),
            $sm->get('Router')
        );
    }
}
