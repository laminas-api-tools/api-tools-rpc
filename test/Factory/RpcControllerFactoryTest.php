<?php

declare(strict_types=1);

namespace LaminasTest\ApiTools\Rpc\Factory;

use Laminas\ApiTools\Rpc\Factory\RpcControllerFactory;
use Laminas\ApiTools\Rpc\RpcController;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\Controller\ControllerManager;
use Laminas\Mvc\Controller\PluginManager;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\Router\RouteMatch as LegacyRouteMatch;
use Laminas\Router\RouteMatch;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\ServiceLocatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

use function class_exists;

class RpcControllerFactoryTest extends TestCase
{
    /** @var ServiceLocatorInterface&MockObject */
    private $services;

    /** @var ControllerManager&MockObject */
    private $controllers;

    /** @var RpcControllerFactory */
    private $factory;

    public function setUp(): void
    {
        $this->services    = $this->createMock(ServiceLocatorInterface::class);
        $this->controllers = $this->createMock(ControllerManager::class);
        $this->controllers->method('getServiceLocator')->willReturn($this->services);

        $this->factory = new RpcControllerFactory();
    }

    /**
     * @param ServiceLocatorInterface&MockObject $container
     */
    private function prepareServiceContainer($container, array $hasMap, array $getMap): void
    {
        $hasMap[] = ['ControllerManager', true];
        $getMap[] = ['ControllerManager', $this->controllers];

        $container->method('has')->will($this->returnValueMap($hasMap));
        $container->method('get')->will($this->returnValueMap($getMap));
    }

    /**
     * @group 7
     */
    public function testWillPullNonCallableStaticCallableFromControllerManagerIfServiceIsPresent(): void
    {
        $config = [
            'api-tools-rpc' => [
                'Controller\Foo' => [
                    'callable' => 'Foo::bar',
                ],
            ],
        ];
        $this->prepareServiceContainer(
            $this->services,
            [['config', true]],
            [['config', $config]]
        );

        $foo = new class {
        };

        $this->controllers->method('has')->with('Foo')->willReturn(true);
        $this->controllers->method('get')->with('Foo')->willReturn($foo);

        $controllers = $this->controllers;

        $this->assertTrue($this->factory->canCreateServiceWithName(
            $controllers,
            'Controller\Foo',
            'Controller\Foo'
        ));
        $controller = $this->factory->createServiceWithName(
            $controllers,
            'Controller\Foo',
            'Controller\Foo'
        );

        $this->assertInstanceOf(RpcController::class, $controller);
        self::assertControllerWrappedCallable([$foo, 'bar'], $controller);
    }

    /**
     * @group 7
     */
    public function testWillPullNonCallableStaticCallableFromServiceManagerIfServiceIsPresent(): void
    {
        $config = [
            'api-tools-rpc' => [
                'Controller\Foo' => [
                    'callable' => 'Foo::bar',
                ],
            ],
        ];

        $foo = new class {
        };

        $this->prepareServiceContainer(
            $this->services,
            [
                ['config', true],
                ['Foo', true],
            ],
            [
                ['config', $config],
                ['Foo', $foo],
            ]
        );

        $this->controllers->method('has')->with('Foo')->willReturn(false);

        $controllers = $this->controllers;

        $this->assertTrue($this->factory->canCreateServiceWithName(
            $controllers,
            'Controller\Foo',
            'Controller\Foo'
        ));
        $controller = $this->factory->createServiceWithName(
            $controllers,
            'Controller\Foo',
            'Controller\Foo'
        );

        $this->assertInstanceOf(RpcController::class, $controller);
        self::assertControllerWrappedCallable([$foo, 'bar'], $controller);
    }

    /**
     * @group 7
     */
    public function testWillInstantiateCallableClassIfClassExists(): void
    {
        $config = [
            'api-tools-rpc' => [
                'Controller\Foo' => [
                    'callable' => TestAsset\Foo::class . '::bar',
                ],
            ],
        ];
        $this->prepareServiceContainer(
            $this->services,
            [
                ['config', true],
                [TestAsset\Foo::class, false],
                [Foo::class, false],
            ],
            [['config', $config]]
        );

        $this->controllers
            ->method('has')
            ->will($this->returnValueMap([
                [TestAsset\Foo::class, false],
                [Foo::class, false],
            ]));

        $controllers = $this->controllers;

        $this->assertTrue($this->factory->canCreateServiceWithName(
            $controllers,
            'Controller\Foo',
            'Controller\Foo'
        ));
        $controller = $this->factory->createServiceWithName(
            $controllers,
            'Controller\Foo',
            'Controller\Foo'
        );

        $this->assertInstanceOf(RpcController::class, $controller);

        $r = new ReflectionProperty($controller, 'wrappedCallable');
        $r->setAccessible(true);
        $callable = $r->getValue($controller);
        self::assertIsArray($callable);
        $this->assertInstanceOf(TestAsset\Foo::class, $callable[0]);
        $this->assertEquals('bar', $callable[1]);
    }

    public function testReportsCannotCreateServiceIfConfigIsMissing(): void
    {
        $this->prepareServiceContainer(
            $this->services,
            [['config', false]],
            []
        );
        $this->assertFalse($this->factory->canCreateServiceWithName(
            $this->controllers,
            'Controller\Foo',
            'Controller\Foo'
        ));
    }

    public function testReportsCannotCreateServiceIfRpcConfigIsMissing(): void
    {
        $this->prepareServiceContainer(
            $this->services,
            [['config', true]],
            [['config', []]],
        );
        $this->assertFalse($this->factory->canCreateServiceWithName(
            $this->controllers,
            'Controller\Foo',
            'Controller\Foo'
        ));
    }

    public function testReportsCannotCreateServiceIfRpcConfigDoesNotContainServiceName(): void
    {
        $this->prepareServiceContainer(
            $this->services,
            [['config', true]],
            [['config', ['api-tools-rpc' => []]]],
        );
        $this->assertFalse($this->factory->canCreateServiceWithName(
            $this->controllers,
            'Controller\Foo',
            'Controller\Foo'
        ));
    }

    public function testReportsCannotCreateServiceIfRpcConfigForControllerIsNotArray(): void
    {
        $this->prepareServiceContainer(
            $this->services,
            [['config', true]],
            [
                [
                    'config',
                    [
                        'api-tools-rpc' => [
                            'Controller\Foo' => true,
                        ],
                    ],
                ],
            ],
        );
        $this->assertFalse($this->factory->canCreateServiceWithName(
            $this->controllers,
            'Controller\Foo',
            'Controller\Foo'
        ));
    }

    public function testReportsCannotCreateServiceIfRpcConfigForControllerDoesNotContainCallableKey(): void
    {
        $this->prepareServiceContainer(
            $this->services,
            [['config', true]],
            [
                [
                    'config',
                    [
                        'api-tools-rpc' => [
                            'Controller\Foo' => [],
                        ],
                    ],
                ],
            ],
        );
        $this->assertFalse($this->factory->canCreateServiceWithName(
            $this->controllers,
            'Controller\Foo',
            'Controller\Foo'
        ));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public function invalidCallables(): array
    {
        return [
            'null'       => [null],
            'zero'       => [0],
            'int'        => [1],
            'zero-float' => [0.0],
            'float'      => [1.1],
            'array'      => [[true, false]],
            'object'     => [(object) []],
        ];
    }

    /**
     * @dataProvider invalidCallables
     * @param mixed $callable
     */
    public function testServiceCreationFailsForInvalidCallable($callable): void
    {
        $this->prepareServiceContainer(
            $this->services,
            [['config', true]],
            [
                [
                    'config',
                    [
                        'api-tools-rpc' => [
                            'Controller\Foo' => [
                                'callable' => $callable,
                            ],
                        ],
                    ],
                ],
            ],
        );
        $this->expectException(ServiceNotCreatedException::class);
        $this->expectExceptionMessage('Unable to create');
        $this->factory->createServiceWithName(
            $this->controllers,
            'Controller\Foo',
            'Controller\Foo'
        );
    }

    /**
     * @return array<string, array{0: callable}>
     */
    public function validCallbacks(): array
    {
        return [
            'function'        => ['is_array'],
            'closure'         => [
                function () {
                },
            ],
            'invokable'       => [new TestAsset\Invokable()],
            'instance-method' => [[new TestAsset\Foo(), 'bar']],
            'static-method'   => [[TestAsset\Foo::class, 'baz']],
        ];
    }

    /**
     * @dataProvider validCallbacks
     */
    public function testServiceCreationReturnsRpcControllerWrappingCallableForValidCallbacks(callable $callable): void
    {
        $this->prepareServiceContainer(
            $this->services,
            [['config', true]],
            [
                [
                    'config',
                    [
                        'api-tools-rpc' => [
                            'Controller\Foo' => [
                                'callable' => $callable,
                            ],
                        ],
                    ],
                ],
            ],
        );
        $controller = $this->factory->createServiceWithName(
            $this->controllers,
            'Controller\Foo',
            'Controller\Foo'
        );

        $this->assertInstanceOf(RpcController::class, $controller);
        self::assertControllerWrappedCallable($callable, $controller);
    }

    /**
     * @see https://github.com/zfcampus/zf-rpc/issues/18
     *
     * @group 7
     */
    public function testFactoryDoesNotEnterACircularDependencyLookupCondition(): void
    {
        $config = [
            'controllers'   => [
                'abstract_factories' => [
                    RpcControllerFactory::class,
                ],
            ],
            'api-tools-rpc' => [
                TestAsset\Foo::class => [
                    'callable' => TestAsset\Foo::class . '::bar',
                ],
            ],
        ];

        $services          = $this->createMock(ServiceLocatorInterface::class);
        $controllerManager = new ControllerManager($services, $config['controllers']);

        $this->prepareServiceContainer(
            $services,
            [
                ['config', true],
                ['ControllerManager', true],
                [TestAsset\Foo::class, false],
                [\ZfTest\Rpc\Factory\TestAsset\Foo::class, false],
            ],
            [
                ['config', $config],
                ['ControllerManager', $controllerManager],
                ['EventManager', $this->createMock(EventManagerInterface::class)],
                ['ControllerPluginManager', $this->createMock(PluginManager::class)],
            ],
        );

        $this->assertTrue($controllerManager->has(TestAsset\Foo::class));

        $controller = $controllerManager->get(TestAsset\Foo::class);
        $this->assertInstanceOf(RpcController::class, $controller);

        $wrappedCallable = self::getControllerWrappedCallable($controller);

        $this->assertInstanceOf(TestAsset\Foo::class, $wrappedCallable[0]);
        $this->assertEquals('bar', $wrappedCallable[1]);

        // The lines below verify that the callable is correctly called when decorated in an RpcController
        $event      = $this->createMock(MvcEvent::class);
        $routeMatch = $this->createMock($this->getRouteMatchClass());
        $event
            ->expects($this->atLeastOnce())
            ->method('getParam')
            ->with('LaminasContentNegotiationParameterData')
            ->willReturn(false);
        $event
            ->expects($this->atLeastOnce())
            ->method('getRouteMatch')
            ->willReturn($routeMatch);
        $event
            ->expects($this->atLeastOnce())
            ->method('setParam')
            ->with('LaminasContentNegotiationFallback', $this->isType('array'));
        $event
            ->expects($this->once())
            ->method('setResult')
            ->with(null);

        $controller->onDispatch($event);
    }

    /**
     * Retrieve the currently expected RouteMatch class.
     *
     * Essentially, these vary between versions 2 and 3 of laminas-mvc, with the
     * latter using the class provided in laminas-router.
     *
     * We can remove this once we drop support for Laminas.
     *
     * @psalm-return class-string
     */
    private function getRouteMatchClass(): string
    {
        if (class_exists(RouteMatch::class)) {
            return RouteMatch::class;
        }
        return LegacyRouteMatch::class;
    }

    /**
     * @return mixed Generally a callable, but not required to be for testing.
     */
    private static function getControllerWrappedCallable(RpcController $controller)
    {
        $reflectionClass    = new ReflectionClass($controller);
        $reflectionProperty = $reflectionClass->getProperty('wrappedCallable');
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($controller);
    }

    /**
     * @param mixed $expected Should be callable, but not required to be for testing.
     */
    private static function assertControllerWrappedCallable($expected, RpcController $controller): void
    {
        $actual = self::getControllerWrappedCallable($controller);

        self::assertSame($expected, $actual);
    }
}
