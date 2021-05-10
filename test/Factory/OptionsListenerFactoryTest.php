<?php

namespace LaminasTest\ApiTools\Rpc\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ApiTools\Rpc\Factory\OptionsListenerFactory;
use Laminas\ApiTools\Rpc\OptionsListener;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ProphecyInterface;

class OptionsListenerFactoryTest extends TestCase
{
    /**
     * @var ContainerInterface|ProphecyInterface
     */
    private $container;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function testWillCreateOptionsListenerWithEmptyConfigWhenConfigServiceIsNotPresent()
    {
        $this->container->has('config')->willReturn(false);
        $factory = new OptionsListenerFactory();

        $listener = $factory($this->container->reveal());
        $this->assertInstanceOf(OptionsListener::class, $listener);
        $this->assertAttributeEquals([], 'config', $listener);
    }

    public function testWillCreateOptionsListenerWithEmptyConfigWhenNoRpcConfigPresent()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(['foo' => 'bar']);
        $factory = new OptionsListenerFactory();

        $listener = $factory($this->container->reveal());
        $this->assertInstanceOf(OptionsListener::class, $listener);
        $this->assertAttributeEquals([], 'config', $listener);
    }

    public function testWillCreateOptionsListenerWithRpcConfigWhenPresent()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn([
            'api-tools-rpc' => [
                'foo' => 'bar',
            ],
        ]);
        $factory = new OptionsListenerFactory();

        $listener = $factory($this->container->reveal());
        $this->assertInstanceOf(OptionsListener::class, $listener);
        $this->assertAttributeEquals(['foo' => 'bar'], 'config', $listener);
    }
}
