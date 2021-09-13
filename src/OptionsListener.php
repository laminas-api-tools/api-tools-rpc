<?php

declare(strict_types=1);

namespace Laminas\ApiTools\Rpc;

use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\EventManager\ListenerAggregateTrait;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Mvc\MvcEvent;
use Stringable;

use function array_key_exists;
use function implode;
use function in_array;
use function is_string;
use function strtoupper;

class OptionsListener implements ListenerAggregateInterface
{
    use ListenerAggregateTrait;

    /** @var array */
    protected $config;

    /**
     * @param  array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_ROUTE, [$this, 'onRoute'], -100);
    }

    /**
     * @return void|Response
     */
    public function onRoute(MvcEvent $event)
    {
        $matches = $event->getRouteMatch();
        if (! $matches) {
            // No matches, nothing to do
            return;
        }

        $controller = $matches->getParam('controller', false);
        if (! $controller) {
            // No controller in the matches, nothing to do
            return;
        }

        if (! array_key_exists($controller, $this->config)) {
            // No matching controller in our configuration, nothing to do
            return;
        }

        $config = $this->config[$controller];

        if (
            ! array_key_exists('http_methods', $config)
            || empty($config['http_methods'])
        ) {
            // No HTTP methods set for controller, nothing to do
            return;
        }

        $request = $event->getRequest();
        if (! $request instanceof Request) {
            // Not an HTTP request? nothing to do
            return;
        }

        $methods = $this->normalizeMethods($config['http_methods']);

        $method = $request->getMethod();
        if ($method === Request::METHOD_OPTIONS) {
            // OPTIONS request? return response with Allow header
            return $this->getOptionsResponse($event, $methods);
        }

        if (in_array($method, $methods)) {
            // Valid HTTP method; nothing to do
            return;
        }

        // Invalid method; return 405 response
        return $this->get405Response($event, $methods);
    }

    /**
     * Normalize an array of HTTP methods
     *
     * If a string is provided, create an array with that string.
     *
     * Ensure all options in the array are UPPERCASE.
     *
     * @param  string|array<string> $methods
     * @return list<string>
     */
    protected function normalizeMethods($methods)
    {
        if (is_string($methods)) {
            $methods = (array) $methods;
        }

        $normalized = [];
        foreach ($methods as $method) {
            $normalized[] = strtoupper($method);
        }

        return $normalized;
    }

    /**
     * Create the Allow header
     *
     * @param array $options
     * @psalm-param array<array-key,null|Stringable|scalar> $options
     * @return void
     */
    protected function createAllowHeader(array $options, Response $response)
    {
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Allow', implode(',', $options));
    }

    /**
     * Prepare and return an OPTIONS response
     *
     * Creates an empty response with an Allow header.
     *
     * @param  array $options
     * @psalm-param array<array-key,null|Stringable|scalar> $options
     * @return Response
     */
    protected function getOptionsResponse(MvcEvent $event, array $options)
    {
        $response = $event->getResponse();
        $this->createAllowHeader($options, $response);
        return $response;
    }

    /**
     * Prepare a 405 response
     *
     * @param  array $options
     * @psalm-param array<array-key,null|Stringable|scalar> $options
     * @return Response
     */
    protected function get405Response(MvcEvent $event, array $options)
    {
        $response = $this->getOptionsResponse($event, $options);
        $response->setStatusCode(405, 'Method Not Allowed');
        return $response;
    }
}
