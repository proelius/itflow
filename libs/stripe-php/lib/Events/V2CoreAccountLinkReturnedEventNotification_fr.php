<?php

// File generated from our OuvertAPI spec

namespace Stripe\Événements;

class V2CoreAccountLinkReturnedEventNotification extends \Stripe\V2\Core\EventNotification
{
    const LOOKUP_TYPE = 'v2.core.account_link.returned';

    /**
     * Retrieves the full event object from the API. Make an API request on every call.
     *
     * @return V2CoreAccountLinkReturnedEvent
     *
     * @throws \Stripe\Exception\ApiErreurException if the request fails
     */
    public function fetchEvent()
    {
        return parent::fetchEvent();
    }
}
