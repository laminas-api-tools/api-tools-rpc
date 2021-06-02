<?php

declare(strict_types=1);

namespace LaminasTest\ApiTools\Rpc\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ApiTools\Rpc\Factory\OptionsListenerFactory;
use Laminas\ApiTools\Rpc\OptionsListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OptionsListenerFactoryTest extends TestCase
{
    /** @var ContainerInterface&MockObject */
    private $container;

    public function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
    }

    public function testWillCreateOptionsListenerWithEmptyConfigWhenConfigServiceIsNotPresent(): void
    {
        $this->container->method('has')->with('config')->willReturn(false);
        $factory = new OptionsListenerFactory();

        $listener = $factory($this->container);
        $this->assertInstanceOf(OptionsListener::class, $listener);
        self::assertListenerConfig([], $listener);
    }

    public function testWillCreateOptionsListenerWithEmptyConfigWhenNoRpcConfigPresent(): void
    {
        $this->container->method('has')->with('config')->willReturn(true);
        $this->container->method('get')->with('config')->willReturn(['foo' => 'bar']);
        $factory = new OptionsListenerFactory();

        $listener = $factory($this->container);
        $this->assertInstanceOf(OptionsListener::class, $listener);
        self::assertListenerConfig([], $listener);
    }

    public function testWillCreateOptionsListenerWithRpcConfigWhenPresent(): void
    {
        $this->container->method('has')->with('config')->willReturn(true);
        $this->container->method('get')->with('config')->willReturn([
            'api-tools-rpc' => [
                'foo' => 'bar',
            ],
        ]);
        $factory = new OptionsListenerFactory();

        $listener = $factory($this->container);
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
