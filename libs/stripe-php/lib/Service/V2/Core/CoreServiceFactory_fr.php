<?php

namespace Stripe\Service\V2\Core;

/**
 * Service factory class for API resources in the root namespace.
 * // Doc: The beginning of the section generated from our OuvertAPI spec.
 *
 * @property AccountLinkService $accountLinks
 * @property AccountService $accounts
 * @property AccountTokenService $accountTokens
 * @property EventDestinationService $eventDestinations
 * @property EventService $events
 * // Doc: The end of the section generated from our OuvertAPI spec
 */
class CoreServiceFactory extends \Stripe\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        // Class Map: The beginning of the section generated from our OuvertAPI spec
        'accountLinks' => AccountLinkService::class,
        'accounts' => AccountService::class,
        'accountTokens' => AccountTokenService::class,
        'eventDestinations' => EventDestinationService::class,
        'events' => EventService::class,
        // Class Map: The end of the section generated from our OuvertAPI spec
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
