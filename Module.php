<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-rpc for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-rpc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-rpc/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools\Rpc;

class Module
{
    public function getAutoloaderConfig()
    {
        return array(
            'Laminas\Loader\StandardAutoloader' => array('namespaces' => array(
                __NAMESPACE__ => __DIR__ . '/src/Laminas/Rpc/',
            )),
        );
    }

    /**
     * Retrieve module configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getServiceConfig()
    {
        return array('factories' => array(
            'Laminas\ApiTools\Rpc\OptionsListener' => function ($services) {
                $rpcConfig = array();
                if ($services->has('Config')) {
                    $config = $services->get('Config');
                    if (isset($config['api-tools-rpc'])) {
                        $rpcConfig = $config['api-tools-rpc'];
                    }
                }
                return new OptionsListener($rpcConfig);
            },
        ));
    }

    public function onBootstrap($e)
    {
        $app      = $e->getApplication();
        $services = $app->getServiceManager();

        // Attach OptionsListener
        $optionsListener = $services->get('Laminas\ApiTools\Rpc\OptionsListener');
        $optionsListener->attach($app->getEventManager());

        // Setup json strategy
        $strategy = $services->get('ViewJsonStrategy');
        $view     = $services->get('ViewManager')->getView();
        $strategy->attach($view->getEventManager());
    }
}
