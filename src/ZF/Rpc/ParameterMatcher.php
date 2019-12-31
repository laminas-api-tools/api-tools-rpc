<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-rpc for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-rpc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-rpc/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\ApiTools\Rpc;

use Laminas\Mvc\MvcEvent;

class ParameterMatcher
{
    protected $mvcEvent = null;

    public function __construct(MvcEvent $mvcEvent)
    {
        $this->mvcEvent = $mvcEvent;
    }

    public function getMatchedParameters($callable, $parameters)
    {
        if (is_string($callable) || $callable instanceof \Closure) {
            $reflection = new \ReflectionFunction($callable);
            $reflMethodParams = $reflection->getParameters();
        } elseif (is_array($callable) && count($callable) == 2) {
            $object = $callable[0];
            $method = $callable[1];
            $reflection = new \ReflectionObject($object);
            $reflMethodParams = $reflection->getMethod($method)->getParameters();
        } else {
            throw new \Exception('Unknown callable');
        }

        $dispatchParams = array();

        // normalize names to that they can match potential php variables
        $normalParams = array();
        foreach ($parameters as $pn => $pv) {
            $normalParams[str_replace(array('-', '_'), '', strtolower($pn))] = $pv;
        }

        foreach ($reflMethodParams as $reflMethodParam) {
            $paramName = $reflMethodParam->getName();
            $normalMethodParamName = str_replace(array('-', '_'), '', strtolower($paramName));
            if ($reflectionTypehint = $reflMethodParam->getClass()) {
                $typehint = $reflectionTypehint->getName();
                if ($typehint == 'Laminas\Http\PhpEnvironment\Request'
                    || $typehint == 'Laminas\Http\Request'
                    || $typehint == 'Laminas\Stdlib\RequestInterface'
                    || $this->isSubclassOf($typehint, 'Laminas\Stdlib\RequestInterface')) {
                    $dispatchParams[] = $this->mvcEvent->getRequest();
                    continue;
                }
                if ($typehint == 'Laminas\Http\PhpEnvironment\Response'
                    || $typehint == 'Laminas\Http\Response'
                    || $typehint == 'Laminas\Stdlib\ResponseInterface'
                    || $this->isSubclassOf($typehint, 'Laminas\Stdlib\ResponseInterface')) {
                    $dispatchParams[] = $this->mvcEvent->getResponse();
                    continue;
                }
                if ($typehint == 'Laminas\Mvc\ApplicationInterface'
                    || $typehint == 'Laminas\Mvc\Application'
                    || $this->isSubclassOf($typehint, 'Laminas\Mvc\ApplicationInterface')) {
                    $dispatchParams[] = $this->mvcEvent->getApplication();
                    continue;
                }
                if ($typehint == 'Laminas\Mvc\MvcEvent'
                    || $this->isSubclassOf($typehint, 'Laminas\Mvc\MvcEvent')) {
                    $dispatchParams[] = $this->mvcEvent;
                    continue;
                }
                throw new \Exception($typehint . ' was requested that could not be auto-bound.');
            } elseif (isset($normalParams[$normalMethodParamName])) {
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


    protected static function isSubclassOf($className, $type)
    {
        if (is_subclass_of($className, $type)) {
            return true;
        }
        if (version_compare(PHP_VERSION, '5.3.7', '>=')) {
            return false;
        }
        if (!interface_exists($type)) {
            return false;
        }
        $r = new \ReflectionClass($className);
        return $r->implementsInterface($type);
    }
}
