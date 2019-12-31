<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-rpc for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-rpc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-rpc/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools\Rpc;

use Closure;
use Laminas\Mvc\Controller\AbstractActionController as BaseAbstractActionController;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Model;

class RpcController extends BaseAbstractActionController
{
    protected $wrappedCallable;

    public function setWrappedCallable($wrappedCallable)
    {
        $this->wrappedCallable = $wrappedCallable;
    }

    public function onDispatch(MvcEvent $e)
    {
        $routeMatch = $e->getRouteMatch();

        $contentNegotiationParams = $e->getParam('LaminasContentNegotiationParameterData');
        if ($contentNegotiationParams) {
            $routeParameters = $contentNegotiationParams->getRouteParams();
        } else {
            $routeParameters = $routeMatch->getParams();
        }

        $parameterMatcher = new ParameterMatcher($e);

        // match route params to dispatchable parameters
        if ($this->wrappedCallable instanceof Closure) {
            $callable = $this->wrappedCallable;
        } elseif (is_array($this->wrappedCallable) && is_callable($this->wrappedCallable)) {
            $callable = $this->wrappedCallable;
        } elseif (is_object($this->wrappedCallable) || is_null($this->wrappedCallable)) {
            $action = $routeMatch->getParam('action', 'not-found');
            $method = static::getMethodFromAction($action);
            $callable = (is_null($this->wrappedCallable) && get_class($this) !== __CLASS__)
                ? $this
                : $this->wrappedCallable;
            if (! method_exists($callable, $method)) {
                $method = 'notFoundAction';
            }
            $callable = [$callable, $method];
        } else {
            throw new \Exception('RPC Controller Not Understood');
        }

        $dispatchParameters = $parameterMatcher->getMatchedParameters($callable, $routeParameters ?: []);
        $result = call_user_func_array($callable, $dispatchParameters);

        $e->setParam('LaminasContentNegotiationFallback', ['Laminas\View\Model\JsonModel' => ['application/json']]);
        $e->setResult($result);
    }

    /**
     * Transform an "action" token into a method name
     *
     * @param  string $action
     * @return string
     */
    public static function getMethodFromAction($action)
    {
        $method  = str_replace(['.', '-', '_'], ' ', $action);
        $method  = ucwords($method);
        $method  = str_replace(' ', '', $method);
        $method  = lcfirst($method);

        return $method;
    }
}
