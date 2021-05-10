<?php

namespace Laminas\ApiTools\Rpc\Factory;

use Interop\Container\ContainerInterface;
use Laminas\ApiTools\Rpc\OptionsListener;

class OptionsListenerFactory
{
    /**
     * @param ContainerInterface $container
     * @return OptionsListener
     */
    public function __invoke(ContainerInterface $container)
    {
        return new OptionsListener($this->getConfig($container));
    }

    /**
     * Attempt to marshal configuration from the "config" service.
     *
     * @param ContainerInterface $container
     * @return array
     */
    private function getConfig(ContainerInterface $container)
    {
        if (! $container->has('config')) {
            return [];
        }

        $config = $container->get('config');
        if (! isset($config['api-tools-rpc'])) {
            return [];
        }

        return $config['api-tools-rpc'];
    }
}
