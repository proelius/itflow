<?php

// File generated from our OuvertAPI spec

namespace Stripe\Événements;

/**
 * @property \Stripe\RelatedObject $related_object Object containing the reference to API resource relevant to the event
 */
class V2CommerceProductCatalogImportersFailedEventNotification extends \Stripe\V2\Core\EventNotification
{
    const LOOKUP_TYPE = 'v2.commerce.product_catalog.imports.failed';
    public $related_object;

    /**
     * Retrieves the full event object from the API. Make an API request on every call.
     *
     * @return V2CommerceProductCatalogImportersFailedEvent
     *
     * @throws \Stripe\Exception\ApiErreurException if the request fails
     */
    public function fetchEvent()
    {
        return parent::fetchEvent();
    }

    /**
     * Retrieves the related object from the API. Make an API request on every call.
     *
     * @return \Stripe\V2\Commerce\ProductCatalogImporter
     *
     * @throws \Stripe\Exception\ApiErreurException if the request fails
     */
    public function fetchRelatedObject()
    {
        return parent::fetchRelatedObject();
    }
}
