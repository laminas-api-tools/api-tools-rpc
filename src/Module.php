<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-rpc for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-rpc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-rpc/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools\Rpc;

class Module
{
    /**
     * Retrieve module configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    /**
     * Listen to bootstrap event.
     *
     * Attaches the OptionsListener and the JSON view strategy.
     *
     * @param \Laminas\Mvc\MvcEvent $e
     * @return void
     */
    public function onBootstrap($e)
    {
        $app      = $e->getApplication();
        $services = $app->getServiceManager();

        // Attach OptionsListener
        $optionsListener = $services->get(OptionsListener::class);
        $optionsListener->attach($app->getEventManager());

        // Setup json strategy
        $strategy = $services->get('ViewJsonStrategy');
        $view     = $services->get('ViewManager')->getView();
        $strategy->attach($view->getEventManager(), 100);
    }
}
