<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-rpc for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-rpc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-rpc/blob/master/LICENSE.md New BSD License
 */

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
