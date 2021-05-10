<?php

namespace Laminas\ApiTools\Rpc;

use Closure;
use Exception;
use Laminas\Http\PhpEnvironment\Request as PhpEnvironmentRequest;
use Laminas\Http\PhpEnvironment\Response as PhpEnvironmentResponse;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Mvc\Application;
use Laminas\Mvc\ApplicationInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\Stdlib\RequestInterface;
use Laminas\Stdlib\ResponseInterface;
use ReflectionFunction;
use ReflectionObject;

class ParameterMatcher
{
    protected $mvcEvent = null;

    public function __construct(MvcEvent $mvcEvent)
    {
        $this->mvcEvent = $mvcEvent;
    }

    public function getMatchedParameters($callable, $parameters)
    {
        if (is_string($callable) || $callable instanceof Closure) {
            $reflection = new ReflectionFunction($callable);
            $reflMethodParams = $reflection->getParameters();
        } elseif (is_array($callable) && count($callable) == 2) {
            $object = $callable[0];
            $method = $callable[1];
            $reflection = new ReflectionObject($object);
            $reflMethodParams = $reflection->getMethod($method)->getParameters();
        } else {
            throw new Exception('Unknown callable');
        }

        $dispatchParams = [];

        // normalize names to that they can match potential php variables
        $normalParams = [];
        foreach ($parameters as $pn => $pv) {
            $normalParams[str_replace(['-', '_'], '', strtolower($pn))] = $pv;
        }

        foreach ($reflMethodParams as $reflMethodParam) {
            $paramName = $reflMethodParam->getName();
            $normalMethodParamName = str_replace(['-', '_'], '', strtolower($paramName));
            if ($reflectionTypehint = $reflMethodParam->getClass()) {
                $typehint = $reflectionTypehint->getName();

                if ($typehint == PhpEnvironmentRequest::class
                    || $typehint == Request::class
                    || $typehint == RequestInterface::class
                    || is_subclass_of($typehint, RequestInterface::class)
                ) {
                    $dispatchParams[] = $this->mvcEvent->getRequest();
                    continue;
                }

                if ($typehint == PhpEnvironmentResponse::class
                    || $typehint == Response::class
                    || $typehint == ResponseInterface::class
                    || is_subclass_of($typehint, ResponseInterface::class)
                ) {
                    $dispatchParams[] = $this->mvcEvent->getResponse();
                    continue;
                }

                if ($typehint == ApplicationInterface::class
                    || $typehint == Application::class
                    || is_subclass_of($typehint, ApplicationInterface::class)
                ) {
                    $dispatchParams[] = $this->mvcEvent->getApplication();
                    continue;
                }

                if ($typehint == MvcEvent::class
                    || is_subclass_of($typehint, MvcEvent::class)
                ) {
                    $dispatchParams[] = $this->mvcEvent;
                    continue;
                }

                throw new Exception(sprintf(
                    '%s was requested, but could not be auto-bound',
                    $typehint
                ));
            }

            if (isset($normalParams[$normalMethodParamName])) {
                $dispatchParams[] = $normalParams[$normalMethodParamName];
            } else {
                if ($reflMethodParam->isOptional()) {
                    $dispatchParams[] = $reflMethodParam->getDefaultValue();
                    continue;
                }
                $dispatchParams[] = null;
            }
        }

        return $dispatchParams;
    }
}
