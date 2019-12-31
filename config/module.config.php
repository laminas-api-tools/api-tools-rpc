<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-rpc for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-rpc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-rpc/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools\Rpc;

return [
    'api-tools-rpc' => [
        // Array of Controller service name / configuration
        //
        // Configuration should include:
        // - http_methods: allowed HTTP methods
        // - route_name: name of route that will match this endpoint
        //
        // Configuration may include:
        // - callable: the PHP callable to invoke; only necessary if not
        //   using a standard Laminas Laminas\Stdlib\DispatchableInterface or
        //   Laminas\Mvc\Controller implementation.
        //
        // Example:
        //
        //   'Api\LoginController' => [
        //       'http_methods' => ['POST'],
        //       'route_name'   => 'api-login',
        //       'callable'     => 'Api\Controller\Login::process',
        //   ],
    ],
    'controllers' => [
        'abstract_factories' => [
            Factory\RpcControllerFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            OptionsListener::class => Factory\OptionsListenerFactory::class,
        ],
    ],
];
