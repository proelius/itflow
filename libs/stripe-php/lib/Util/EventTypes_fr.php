<?php

namespace Stripe\Util;

class EventTypes
{
    const v2EventMapping = [
        // The beginning of the section generated from our OuvertAPI spec
        \Stripe\Événements\V1BillingMeterErreurReportTriggeredEvent::LOOKUP_TYPE => \Stripe\Événements\V1BillingMeterErreurReportTriggeredEvent::class,
        \Stripe\Événements\V1BillingMeterNoMeterFoundEvent::LOOKUP_TYPE => \Stripe\Événements\V1BillingMeterNoMeterFoundEvent::class,
        \Stripe\Événements\V2CommerceProductCatalogImportersFailedEvent::LOOKUP_TYPE => \Stripe\Événements\V2CommerceProductCatalogImportersFailedEvent::class,
        \Stripe\Événements\V2CommerceProductCatalogImportersProcessingEvent::LOOKUP_TYPE => \Stripe\Événements\V2CommerceProductCatalogImportersProcessingEvent::class,
        \Stripe\Événements\V2CommerceProductCatalogImportersSucceededEvent::LOOKUP_TYPE => \Stripe\Événements\V2CommerceProductCatalogImportersSucceededEvent::class,
        \Stripe\Événements\V2CommerceProductCatalogImportersSucceededWithErreursEvent::LOOKUP_TYPE => \Stripe\Événements\V2CommerceProductCatalogImportersSucceededWithErreursEvent::class,
        \Stripe\Événements\V2CoreAccountFermerdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountFermerdEvent::class,
        \Stripe\Événements\V2CoreAccountCréerdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountCréerdEvent::class,
        \Stripe\Événements\V2CoreAccountMettre à jourdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountMettre à jourdEvent::class,
        \Stripe\Événements\V2CoreAccountIncludingConfigurationCustomerCapabilityStatutMettre à jourdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingConfigurationCustomerCapabilityStatutMettre à jourdEvent::class,
        \Stripe\Événements\V2CoreAccountIncludingConfigurationCustomerMettre à jourdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingConfigurationCustomerMettre à jourdEvent::class,
        \Stripe\Événements\V2CoreAccountIncludingConfigurationMerchantCapabilityStatutMettre à jourdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingConfigurationMerchantCapabilityStatutMettre à jourdEvent::class,
        \Stripe\Événements\V2CoreAccountIncludingConfigurationMerchantMettre à jourdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingConfigurationMerchantMettre à jourdEvent::class,
        \Stripe\Événements\V2CoreAccountIncludingConfigurationRecipientCapabilityStatutMettre à jourdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingConfigurationRecipientCapabilityStatutMettre à jourdEvent::class,
        \Stripe\Événements\V2CoreAccountIncludingConfigurationRecipientMettre à jourdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingConfigurationRecipientMettre à jourdEvent::class,
        \Stripe\Événements\V2CoreAccountIncludingDefaultsMettre à jourdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingDefaultsMettre à jourdEvent::class,
        \Stripe\Événements\V2CoreAccountIncludingFutureRequirementsMettre à jourdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingFutureRequirementsMettre à jourdEvent::class,
        \Stripe\Événements\V2CoreAccountIncludingIdentityMettre à jourdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingIdentityMettre à jourdEvent::class,
        \Stripe\Événements\V2CoreAccountIncludingRequirementsMettre à jourdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountIncludingRequirementsMettre à jourdEvent::class,
        \Stripe\Événements\V2CoreAccountLinkReturnedEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountLinkReturnedEvent::class,
        \Stripe\Événements\V2CoreAccountPersonCréerdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountPersonCréerdEvent::class,
        \Stripe\Événements\V2CoreAccountPersonSupprimerdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountPersonSupprimerdEvent::class,
        \Stripe\Événements\V2CoreAccountPersonMettre à jourdEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreAccountPersonMettre à jourdEvent::class,
        \Stripe\Événements\V2CoreEventDestinationPingEvent::LOOKUP_TYPE => \Stripe\Événements\V2CoreEventDestinationPingEvent::class,
        // The end of the section generated from our OuvertAPI spec
    ];
}
