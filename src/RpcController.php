<?php

declare(strict_types=1);

namespace Laminas\ApiTools\Rpc;

use Closure;
use Exception;
use Laminas\Mvc\Controller\AbstractActionController as BaseAbstractActionController;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Model\JsonModel;

use function call_user_func_array;
use function is_array;
use function is_callable;
use function is_object;
use function lcfirst;
use function method_exists;
use function str_replace;
use function ucwords;

class RpcController extends BaseAbstractActionController
{
    /** @var null|callable */
    protected $wrappedCallable;

    /**
     * @param callable $wrappedCallable
     * @return void
     */
    public function setWrappedCallable($wrappedCallable)
    {
        $this->wrappedCallable = $wrappedCallable;
    }

    /**
     * @return void
     */
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
        } elseif (is_object($this->wrappedCallable) || null === $this->wrappedCallable) {
            $action   = $routeMatch->getParam('action', 'not-found');
            $method   = static::getMethodFromAction($action);
            $callable = null === $this->wrappedCallable && static::class !== self::class
                ? $this
                : $this->wrappedCallable;
            if (! method_exists($callable, $method)) {
                $method = 'notFoundAction';
            }
            $callable = [$callable, $method];
        } else {
            throw new Exception('RPC Controller Not Understood');
        }

        $dispatchParameters = $parameterMatcher->getMatchedParameters($callable, $routeParameters ?: []);
        $result             = call_user_func_array($callable, $dispatchParameters);

        $e->setParam('LaminasContentNegotiationFallback', [JsonModel::class => ['application/json']]);
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
        $method = str_replace(['.', '-', '_'], ' ', $action);
        $method = ucwords($method);
        $method = str_replace(' ', '', $method);
        $method = lcfirst($method);

        return $method;
    }
}
