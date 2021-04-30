<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-rpc for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-rpc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-rpc/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\ApiTools\Rpc\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ApiTools\Rpc\Factory\OptionsListenerFactory;
use Laminas\ApiTools\Rpc\OptionsListener;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ProphecyInterface;
use ReflectionClass;

class OptionsListenerFactoryTest extends TestCase
{
    use ProphecyTrait;

    /** @var ContainerInterface|ProphecyInterface */
    private $container;

    public function setUp(): void
    {
        $this->container = $this->prophesize(ContainerInterface::class);
    }

    public function testWillCreateOptionsListenerWithEmptyConfigWhenConfigServiceIsNotPresent()
    {
        $this->container->has('config')->willReturn(false);
        $factory = new OptionsListenerFactory();

        $listener = $factory($this->container->reveal());
        $this->assertInstanceOf(OptionsListener::class, $listener);
        self::assertListenerConfig([], $listener);
    }

    public function testWillCreateOptionsListenerWithEmptyConfigWhenNoRpcConfigPresent()
    {
        $this->container->has('config')->willReturn(true);
        $this->container->get('config')->willReturn(['foo' => 'bar']);
        $factory = new OptionsListenerFactory();

        $listener = $factory($this->container->reveal());
        $this->assertInstanceOf(OptionsListener::class, $listener);
        self::assertListenerConfig([], $listener);
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
        self::assertListenerConfig(['foo' => 'bar'], $listener);
    }

    /**
     * @param array $expected
     */
    private static function assertListenerConfig(array $expected, OptionsListener $listener): void
    {
        $reflectionClass    = new ReflectionClass($listener);
        $reflectionProperty = $reflectionClass->getProperty('config');
        $reflectionProperty->setAccessible(true);
        $actual = $reflectionProperty->getValue($listener);

        self::assertEquals($expected, $actual);
    }
}
