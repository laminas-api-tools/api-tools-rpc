<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-rpc for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-rpc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-rpc/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\ApiTools\Rpc\Factory;

use Laminas\ApiTools\Rpc\Factory\RpcControllerFactory;
use PHPUnit_Framework_TestCase as TestCase;
use ReflectionProperty;

class RpcControllerFactoryTest extends TestCase
{
    public function setUp()
    {
        $this->services = $services = $this->prophesize('Laminas\ServiceManager\ServiceLocatorInterface');
        $this->controllers = $this->prophesize('Laminas\Mvc\Controller\ControllerManager');
        $this->controllers->getServiceLocator()->will(function () use ($services) {
            return $services->reveal();
        });
        $this->factory  = new RpcControllerFactory();
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
        $this->services->has('Config')->willReturn(true);
        $this->services->get('Config')->willReturn($config);

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

        $this->assertInstanceOf('Laminas\ApiTools\Rpc\RpcController', $controller);
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
        $this->services->has('Config')->willReturn(true);
        $this->services->get('Config')->willReturn($config);

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

        $this->assertInstanceOf('Laminas\ApiTools\Rpc\RpcController', $controller);
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
                    'callable' => 'LaminasTest\ApiTools\Rpc\Factory\TestAsset\Foo::bar',
                ],
            ],
        ];
        $this->services->has('Config')->willReturn(true);
        $this->services->get('Config')->willReturn($config);

        $this->controllers->has('LaminasTest\ApiTools\Rpc\Factory\TestAsset\Foo')->willReturn(false);
        $this->services->has('LaminasTest\ApiTools\Rpc\Factory\TestAsset\Foo')->willReturn(false);

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

        $this->assertInstanceOf('Laminas\ApiTools\Rpc\RpcController', $controller);

        $r = new ReflectionProperty($controller, 'wrappedCallable');
        $r->setAccessible(true);
        $callable = $r->getValue($controller);
        $this->assertInternalType('array', $callable);
        $this->assertInstanceOf(__NAMESPACE__ . '\TestAsset\Foo', $callable[0]);
        $this->assertEquals('bar', $callable[1]);
    }

    public function testReportsCannotCreateServiceIfConfigIsMissing()
    {
        $this->services->has('Config')->willReturn(false);
        $this->assertFalse($this->factory->canCreateServiceWithName(
            $this->controllers->reveal(),
            'Controller\Foo',
            'Controller\Foo'
        ));
    }

    public function testReportsCannotCreateServiceIfRpcConfigIsMissing()
    {
        $this->services->has('Config')->willReturn(true);
        $this->services->get('Config')->willReturn([]);
        $this->assertFalse($this->factory->canCreateServiceWithName(
            $this->controllers->reveal(),
            'Controller\Foo',
            'Controller\Foo'
        ));
    }

    public function testReportsCannotCreateServiceIfRpcConfigDoesNotContainServiceName()
    {
        $this->services->has('Config')->willReturn(true);
        $this->services->get('Config')->willReturn(['api-tools-rpc' => []]);
        $this->assertFalse($this->factory->canCreateServiceWithName(
            $this->controllers->reveal(),
            'Controller\Foo',
            'Controller\Foo'
        ));
    }

    public function testReportsCannotCreateServiceIfRpcConfigForControllerIsNotArray()
    {
        $this->services->has('Config')->willReturn(true);
        $this->services->get('Config')->willReturn(['api-tools-rpc' => [
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
        $this->services->has('Config')->willReturn(true);
        $this->services->get('Config')->willReturn(['api-tools-rpc' => [
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
     */
    public function testServiceCreationFailsForInvalidCallable($callable)
    {
        $this->services->get('Config')->willReturn(['api-tools-rpc' => [
            'Controller\Foo' => [
                'callable' => $callable,
            ],
        ]]);
        $this->setExpectedException('InvalidArgumentException', 'Unable to create');
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
            'static-method'   => [[__NAMESPACE__ . '\TestAsset\Foo', 'baz']],
        ];
    }

    /**
     * @dataProvider validCallbacks
     */
    public function testServiceCreationReturnsRpcControllerWrappingCallableForValidCallbacks($callable)
    {
        $this->services->get('Config')->willReturn(['api-tools-rpc' => [
            'Controller\Foo' => [
                'callable' => $callable,
            ],
        ]]);
        $controller = $this->factory->createServiceWithName(
            $this->controllers->reveal(),
            'Controller\Foo',
            'Controller\Foo'
        );

        $this->assertInstanceOf('Laminas\ApiTools\Rpc\RpcController', $controller);
        $this->assertAttributeSame($callable, 'wrappedCallable', $controller);
    }
}
