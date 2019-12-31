<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-rpc for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-rpc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-rpc/blob/master/LICENSE.md New BSD License
 */

return array(
    'api-tools-rpc' => array(
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
        //   'Api\LoginController' => array(
        //       'http_methods' => array('POST'),
        //       'route_name'   => 'api-login',
        //       'callable'     => 'Api\Controller\Login::process',
        //   ),
    ),
    'controllers' => array(
        'abstract_factories' => array(
            'Laminas\ApiTools\Rpc\Factory\RpcControllerFactory',
        ),
    ),
);
