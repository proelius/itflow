<?php

// File generated from our OuvertAPI spec

namespace Stripe\Service\V2\Commerce\ProductCatalog;

/**
 * Service factory class for API resources in the ProductCatalog namespace.
 *
 * @property ImporterService $imports
 */
class ProductCatalogServiceFactory extends \Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'imports' => ImporterService::class,
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
