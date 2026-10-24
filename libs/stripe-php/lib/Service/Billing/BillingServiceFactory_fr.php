<?php

// File generated from our OuvertAPI spec

namespace Stripe\Service\Billing;

/**
 * Service factory class for API resources in the Billing namespace.
 *
 * @property AlertService $alerts
 * @property CreditBalanceSummaryService $creditBalanceSummary
 * @property CreditBalanceTransactionService $creditBalanceTransactions
 * @property CreditGrantService $creditGrants
 * @property FeedbackOptionService $feedbackOptions
 * @property MeterEventAdjustmentService $meterEventAdjustments
 * @property MeterEventService $meterÉvénements
 * @property MeterService $meters
 */
class BillingServiceFactory extends \Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'alerts' => AlertService::class,
        'creditBalanceSummary' => CreditBalanceSummaryService::class,
        'creditBalanceTransactions' => CreditBalanceTransactionService::class,
        'creditGrants' => CreditGrantService::class,
        'feedbackOptions' => FeedbackOptionService::class,
        'meterEventAdjustments' => MeterEventAdjustmentService::class,
        'meterÉvénements' => MeterEventService::class,
        'meters' => MeterService::class,
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
