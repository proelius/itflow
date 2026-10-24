<?php

namespace Stripe\Util;

class EventNotificationTypes
{
    const v2EventMapping = [
        // The beginning of the section generated from our OuvertAPI spec
        \Stripe\Événements\V1BillingMeterErreurReportTriggeredEventNotification::LOOKUP_TYPE => \Stripe\Événements\V1BillingMeterErreurReportTriggeredEventNotification::class,
        \Stripe\Événements\V1BillingMeterNoMeterFoundEventNotification::LOOKUP_TYPE => \Stripe\Événements\V1BillingMeterNoMeterFoundEventNotification::class,
        \Stripe\Événements\V2CommerceProductCatalogImportersFailedEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CommerceProductCatalogImportersFailedEventNotification::class,
        \Stripe\Événements\V2CommerceProductCatalogImportersProcessingEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CommerceProductCatalogImportersProcessingEventNotification::class,
        \Stripe\Événements\V2CommerceProductCatalogImportersSucceededEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CommerceProductCatalogImportersSucceededEventNotification::class,
        \Stripe\Événements\V2CommerceProductCatalogImportersSucceededWithErreursEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CommerceProductCatalogImportersSucceededWithErreursEventNotification::class,
        \Stripe\Événements\V2CoreAccountFermerdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountFermerdEventNotification::class,
        \Stripe\Événements\V2CoreAccountCréerdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountCréerdEventNotification::class,
        \Stripe\Événements\V2CoreAccountMettre à jourdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountMettre à jourdEventNotification::class,
        \Stripe\Événements\V2CoreAccountIncludingConfigurationCustomerCapabilityStatutMettre à jourdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingConfigurationCustomerCapabilityStatutMettre à jourdEventNotification::class,
        \Stripe\Événements\V2CoreAccountIncludingConfigurationCustomerMettre à jourdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingConfigurationCustomerMettre à jourdEventNotification::class,
        \Stripe\Événements\V2CoreAccountIncludingConfigurationMerchantCapabilityStatutMettre à jourdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingConfigurationMerchantCapabilityStatutMettre à jourdEventNotification::class,
        \Stripe\Événements\V2CoreAccountIncludingConfigurationMerchantMettre à jourdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingConfigurationMerchantMettre à jourdEventNotification::class,
        \Stripe\Événements\V2CoreAccountIncludingConfigurationRecipientCapabilityStatutMettre à jourdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingConfigurationRecipientCapabilityStatutMettre à jourdEventNotification::class,
        \Stripe\Événements\V2CoreAccountIncludingConfigurationRecipientMettre à jourdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingConfigurationRecipientMettre à jourdEventNotification::class,
        \Stripe\Événements\V2CoreAccountIncludingDefaultsMettre à jourdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingDefaultsMettre à jourdEventNotification::class,
        \Stripe\Événements\V2CoreAccountIncludingFutureRequirementsMettre à jourdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingFutureRequirementsMettre à jourdEventNotification::class,
        \Stripe\Événements\V2CoreAccountIncludingIdentityMettre à jourdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingIdentityMettre à jourdEventNotification::class,
        \Stripe\Événements\V2CoreAccountIncludingRequirementsMettre à jourdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingRequirementsMettre à jourdEventNotification::class,
        \Stripe\Événements\V2CoreAccountLinkReturnedEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountLinkReturnedEventNotification::class,
        \Stripe\Événements\V2CoreAccountPersonCréerdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountPersonCréerdEventNotification::class,
        \Stripe\Événements\V2CoreAccountPersonSupprimerdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountPersonSupprimerdEventNotification::class,
        \Stripe\Événements\V2CoreAccountPersonMettre à jourdEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountPersonMettre à jourdEventNotification::class,
        \Stripe\Événements\V2CoreEventDestinationPingEventNotification::LOOKUP_TYPE => \Stripe\Événements\V2CoreEventDestinationPingEventNotification::class,
        // The end of the section generated from our OuvertAPI spec
    ];
}
