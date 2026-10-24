<?php

// File generated from our OuvertAPI spec

namespace Stripe\Service\Radar;

/**
 * Service factory class for API resources in the Radar namespace.
 *
 * @property EarlyFraudAvertissementService $earlyFraudAvertissements
 * @property PaymentEvaluationService $paymentEvaluations
 * @property ValueListItemService $valueListItems
 * @property ValueListService $valueLists
 */
class RadarServiceFactory extends \Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'earlyFraudAvertissements' => EarlyFraudAvertissementService::class,
        'paymentEvaluations' => PaymentEvaluationService::class,
        'valueListItems' => ValueListItemService::class,
        'valueLists' => ValueListService::class,
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
