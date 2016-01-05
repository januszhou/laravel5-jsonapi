<?php
/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 4/01/16
 * Time: 0:06.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace NilPortugues\Laravel5\JsonApi\Providers;

use NilPortugues\Api\JsonApi\JsonApiTransformer;
use NilPortugues\Api\Mapping\Mapping;
use NilPortugues\Laravel5\JsonApi\JsonApiSerializer;
use NilPortugues\Laravel5\JsonApi\Mapper\Mapper;
use ReflectionClass;

/**
 * Class Laravel51Provider.
 */
class Laravel51Provider
{
    public function provider()
    {
        return function ($app) {
            $mapping = $app['config']->get('jsonapi');
            $transformer = new JsonApiTransformer($this->parseRoutes(new Mapper($mapping)));

            $cacheableConfig = function () use ($transformer) {
                return new JsonApiSerializer($transformer);
            };

            return $cacheableConfig();
        };
    }

    /**
     * @param Mapper $mapper
     *
     * @return Mapper
     */
    protected function parseRoutes(Mapper $mapper)
    {
        foreach ($mapper->getClassMap() as &$mapping) {
            $mappingClass = new \ReflectionClass($mapping);

            $this->setUrlWithReflection($mapping, $mappingClass, 'resourceUrlPattern');
            $this->setUrlWithReflection($mapping, $mappingClass, 'selfUrl');
            $mappingProperty = $mappingClass->getProperty('otherUrls');
            $mappingProperty->setAccessible(true);

            $otherUrls = (array) $mappingProperty->getValue($mapping);
            if (!empty($otherUrls)) {
                foreach ($otherUrls as &$url) {
                    if (!empty($url['name'])) {
                        $url = $this->calculateRoute($url);
                    }
                }
            }
            $mappingProperty->setValue($mapping, $otherUrls);

            $this->setJsonApiRelationships($mappingClass, $mapping);
        }

        return $mapper;
    }

    /**
     * @param Mapping         $mapping
     * @param ReflectionClass $mappingClass
     * @param string          $property
     */
    protected function setUrlWithReflection(Mapping $mapping, ReflectionClass $mappingClass, $property)
    {
        $mappingProperty = $mappingClass->getProperty($property);
        $mappingProperty->setAccessible(true);
        $value = $mappingProperty->getValue($mapping);

        if (!empty($value['name'])) {
            $route = $this->calculateRoute($value);
            $mappingProperty->setValue($mapping, $route);
        }
    }

    /**
     * @param ReflectionClass $mappingClass
     * @param                 $mapping
     */
    protected function setJsonApiRelationships(ReflectionClass $mappingClass, $mapping)
    {
        $mappingProperty = $mappingClass->getProperty('relationshipSelfUrl');
        $mappingProperty->setAccessible(true);

        $relationshipSelfUrl = (array) $mappingProperty->getValue($mapping);
        if (!empty($relationshipSelfUrl)) {
            foreach ($relationshipSelfUrl as &$urlMember) {
                if (!empty($urlMember)) {
                    foreach ($urlMember as &$url) {
                        if (!empty($url['name'])) {
                            $url = $this->calculateRoute($url);
                        }
                    }
                }
            }
        }
        $mappingProperty->setValue($mapping, $relationshipSelfUrl);
    }

    /**
     * @param array $value
     *
     * @return mixed|string
     */
    protected function calculateRoute(array $value)
    {
        $route = urldecode(route($value['name']));

        if (!empty($value['as_id'])) {
            preg_match_all('/{(.*?)}/', $route, $matches);
            $route = str_replace($matches[0], '{'.$value['as_id'].'}', $route);
        }

        return $route;
    }
}
