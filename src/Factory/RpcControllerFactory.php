<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-rpc for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-rpc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-rpc/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools\Rpc\Factory;

use InvalidArgumentException;
use Laminas\ApiTools\Rpc\RpcController;
use Laminas\ServiceManager\AbstractFactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

class RpcControllerFactory implements AbstractFactoryInterface
{
    /**
     * Determine if we can create a service with name
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param $name
     * @param $requestedName
     * @return bool
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $controllerManager, $name, $requestedName)
    {
        $serviceLocator = $controllerManager->getServiceLocator();

        if (! $serviceLocator->has('Config')) {
            return false;
        }

        $config = $serviceLocator->get('Config');
        if (! isset($config['api-tools-rpc'])
            || ! isset($config['api-tools-rpc'][$requestedName])
        ) {
            return false;
        }

        $config = $config['api-tools-rpc'][$requestedName];

        if (! is_array($config)
            || ! isset($config['callable'])
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param ServiceLocatorInterface $controllerManager
     * @param $name
     * @param $requestedName
     * @return mixed|RpcController
     * @throws \Exception
     */
    public function createServiceWithName(ServiceLocatorInterface $controllerManager, $name, $requestedName)
    {
        $serviceLocator = $controllerManager->getServiceLocator();
        $config         = $serviceLocator->get('Config');
        $callable       = $config['api-tools-rpc'][$requestedName]['callable'];

        if (! is_string($callable) && ! is_callable($callable)) {
            throw new InvalidArgumentException('Unable to create a controller from the configured api-tools-rpc callable');
        }

        if (is_string($callable)
            && strpos($callable, '::') !== false
        ) {
            $callable = $this->marshalCallable($callable, $controllerManager, $serviceLocator);
        }

        $controller = new RpcController();
        $controller->setWrappedCallable($callable);
        return $controller;
    }

    /**
     * Marshal an instance method callback from a given string.
     *
     * @param mixed $string String of the form class::method
     * @param ServiceLocatorInterface $controllers
     * @param ServiceLocatorInterface $services
     * @return callable
     */
    private function marshalCallable($string, ServiceLocatorInterface $controllers, ServiceLocatorInterface $services)
    {
        list($class, $method) = explode('::', $string, 2);

        if ($controllers->has($class)) {
            return array($controllers->get($class), $method);
        }

        if ($services->has($class)) {
            return array($services->get($class), $method);
        }

        if (! class_exists($class)) {
            throw new InvalidArgumentException(sprintf(
                'Cannot create callback %s as class %s does not exist',
                $string,
                $class
            ));
        }

        return array(new $class(), $method);
    }
}
