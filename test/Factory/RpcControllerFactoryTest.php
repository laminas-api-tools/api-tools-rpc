<?php

namespace LaminasTest\ApiTools\Rpc\Factory;

use Interop\Container\ContainerInterface;
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
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ProphecyInterface;
use ReflectionProperty;

class RpcControllerFactoryTest extends TestCase
{
    /**
     * @var ServiceLocatorInterface|ProphecyInterface
     */
    private $services;

    /**
     * @var ControllerManager|ProphecyInterface
     */
    private $controllers;

    /**
     * @var RpcControllerFactory
     */
    private $factory;

    public function setUp()
    {
        $this->services = $services = $this->prophesize(ServiceLocatorInterface::class);
        $services->willImplement(ContainerInterface::class);

        $this->controllers = $this->prophesize(ControllerManager::class);
        $this->controllers->getServiceLocator()->willReturn($services->reveal());

        $services->has('ControllerManager')->willReturn(true);
        $services->get('ControllerManager')->willReturn($this->controllers->reveal());

        $this->factory = new RpcControllerFactory();
    }

    /**
     * @group 7
     */
    public function testWillPullNonCallableStaticCallableFromControllerManagerIfServiceIsPresent()
    {
        $config = [
            'api-tools-rpc' => [
                'Controller\Foo' => [
                    'callable' => 'Foo::bar',
                ],
            ],
        ];
        $this->services->has('config')->willReturn(true);
        $this->services->get('config')->willReturn($config);

        $foo = $this->prophesize('stdClass');
        $this->controllers->has('Foo')->willReturn(true);
        $this->controllers->get('Foo')->willReturn($foo->reveal());

        $controllers = $this->controllers->reveal();

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
        $this->assertAttributeSame([$foo->reveal(), 'bar'], 'wrappedCallable', $controller);
    }

    /**
     * @group 7
     */
    public function testWillPullNonCallableStaticCallableFromServiceManagerIfServiceIsPresent()
    {
        $config = [
            'api-tools-rpc' => [
                'Controller\Foo' => [
                    'callable' => 'Foo::bar',
                ],
            ],
        ];
        $this->services->has('config')->willReturn(true);
        $this->services->get('config')->willReturn($config);

        $foo = $this->prophesize('stdClass');
        $this->services->has('Foo')->willReturn(true);
        $this->services->get('Foo')->willReturn($foo->reveal());

        $this->controllers->has('Foo')->willReturn(false);

        $controllers = $this->controllers->reveal();

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
        $this->assertAttributeSame([$foo->reveal(), 'bar'], 'wrappedCallable', $controller);
    }

    /**
     * @group 7
     */
    public function testWillInstantiateCallableClassIfClassExists()
    {
        $config = [
            'api-tools-rpc' => [
                'Controller\Foo' => [
                    'callable' => TestAsset\Foo::class . '::bar',
                ],
            ],
        ];
        $this->services->has('config')->willReturn(true);
        $this->services->get('config')->willReturn($config);

        $this->controllers->has(TestAsset\Foo::class)->willReturn(false);

        $this->controllers->has(\ZFTest\Rpc\Factory\TestAsset\Foo::class)->willReturn(false);
        $this->services->has(TestAsset\Foo::class)->willReturn(false);

        $this->services->has(\ZFTest\Rpc\Factory\TestAsset\Foo::class)->willReturn(false);

        $controllers = $this->controllers->reveal();

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
        $this->assertInternalType('array', $callable);
        $this->assertInstanceOf(TestAsset\Foo::class, $callable[0]);
        $this->assertEquals('bar', $callable[1]);
    }

    public function testReportsCannotCreateServiceIfConfigIsMissing()
    {
        $this->services->has('config')->willReturn(false);
        $this->assertFalse($this->factory->canCreateServiceWithName(
            $this->controllers->reveal(),
            'Controller\Foo',
            'Controller\Foo'
        ));
    }

    public function testReportsCannotCreateServiceIfRpcConfigIsMissing()
    {
        $this->services->has('config')->willReturn(true);
        $this->services->get('config')->willReturn([]);
        $this->assertFalse($this->factory->canCreateServiceWithName(
            $this->controllers->reveal(),
            'Controller\Foo',
            'Controller\Foo'
        ));
    }

    public function testReportsCannotCreateServiceIfRpcConfigDoesNotContainServiceName()
    {
        $this->services->has('config')->willReturn(true);
        $this->services->get('config')->willReturn(['api-tools-rpc' => []]);
        $this->assertFalse($this->factory->canCreateServiceWithName(
            $this->controllers->reveal(),
            'Controller\Foo',
            'Controller\Foo'
        ));
    }

    public function testReportsCannotCreateServiceIfRpcConfigForControllerIsNotArray()
    {
        $this->services->has('config')->willReturn(true);
        $this->services->get('config')->willReturn(['api-tools-rpc' => [
            'Controller\Foo' => true,
        ]]);
        $this->assertFalse($this->factory->canCreateServiceWithName(
            $this->controllers->reveal(),
            'Controller\Foo',
            'Controller\Foo'
        ));
    }

    public function testReportsCannotCreateServiceIfRpcConfigForControllerDoesNotContainCallableKey()
    {
        $this->services->has('config')->willReturn(true);
        $this->services->get('config')->willReturn(['api-tools-rpc' => [
            'Controller\Foo' => [],
        ]]);
        $this->assertFalse($this->factory->canCreateServiceWithName(
            $this->controllers->reveal(),
            'Controller\Foo',
            'Controller\Foo'
        ));
    }

    public function invalidCallables()
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
     *
     * @param mixed $callable
     */
    public function testServiceCreationFailsForInvalidCallable($callable)
    {
        $this->services->get('config')->willReturn(['api-tools-rpc' => [
            'Controller\Foo' => [
                'callable' => $callable,
            ],
        ]]);
        $this->expectException(ServiceNotCreatedException::class);
        $this->expectExceptionMessage('Unable to create');
        $this->factory->createServiceWithName(
            $this->controllers->reveal(),
            'Controller\Foo',
            'Controller\Foo'
        );
    }

    public function validCallbacks()
    {
        return [
            'function'        => ['is_array'],
            'closure'         => [function () {
            }],
            'invokable'       => [new TestAsset\Invokable()],
            'instance-method' => [[new TestAsset\Foo(), 'bar']],
            'static-method'   => [[TestAsset\Foo::class, 'baz']],
        ];
    }

    /**
     * @dataProvider validCallbacks
     *
     * @param callable $callable
     */
    public function testServiceCreationReturnsRpcControllerWrappingCallableForValidCallbacks($callable)
    {
        $this->services->get('config')->willReturn(['api-tools-rpc' => [
            'Controller\Foo' => [
                'callable' => $callable,
            ],
        ]]);
        $controller = $this->factory->createServiceWithName(
            $this->controllers->reveal(),
            'Controller\Foo',
            'Controller\Foo'
        );

        $this->assertInstanceOf(RpcController::class, $controller);
        $this->assertAttributeSame($callable, 'wrappedCallable', $controller);
    }

    /**
     * @group 7
     * @see https://github.com/zfcampus/zf-rpc/issues/18
     */
    public function testFactoryDoesNotEnterACircularDependencyLookupCondition()
    {
        $config = [
            'controllers' => [
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

        $this->services->has('config')->willReturn(true);
        $this->services->get('config')->willReturn($config);

        $this->services->has(TestAsset\Foo::class)->willReturn(false);

        $this->services->has(\ZFTest\Rpc\Factory\TestAsset\Foo::class)->willReturn(false);

        $this->services->get('EventManager')->willReturn($this->prophesize(EventManagerInterface::class)->reveal());
        $this->services->get('ControllerPluginManager')->willReturn($this->prophesize(PluginManager::class)->reveal());

        $controllerManager = new ControllerManager($this->services->reveal(), $config['controllers']);

        $this->services->has('ControllerManager')->willReturn(true);
        $this->services->get('ControllerManager')->willReturn($controllerManager);

        $this->assertTrue($controllerManager->has(TestAsset\Foo::class));

        $controller = $controllerManager->get(TestAsset\Foo::class);
        $this->assertInstanceOf(RpcController::class, $controller);

        $wrappedCallable = $this->readAttribute($controller, 'wrappedCallable');

        $this->assertInstanceOf(TestAsset\Foo::class, $wrappedCallable[0]);
        $this->assertEquals('bar', $wrappedCallable[1]);

        // The lines below verify that the callable is correctly called when decorated in an RpcController
        $event = $this->prophesize(MvcEvent::class);
        $routeMatch = $this->prophesize($this->getRouteMatchClass());
        $event->getParam('LaminasContentNegotiationParameterData')->shouldBeCalled()->willReturn(false);
        $event->getRouteMatch()->shouldBeCalled()->willReturn($routeMatch->reveal());
        $event->setParam('LaminasContentNegotiationFallback', Argument::type('array'))->shouldBeCalled();
        $event->setResult(null)->shouldBeCalled();

        $controller->onDispatch($event->reveal());
    }

    /**
     * Retrieve the currently expected RouteMatch class.
     *
     * Essentially, these vary between versions 2 and 3 of laminas-mvc, with the
     * latter using the class provided in laminas-router.
     *
     * We can remove this once we drop support for Laminas.
     *
     * @return string
     */
    private function getRouteMatchClass()
    {
        if (class_exists(RouteMatch::class)) {
            return RouteMatch::class;
        }
        return LegacyRouteMatch::class;
    }
}
