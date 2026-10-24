<?php

// File generated from our OuvertAPI spec

namespace Stripe\Service\Tax;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 *
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 */
class AssociationService extends \Stripe\Service\AbstractService
{
    /**
     * Finds a tax association object by PaymentIntent id.
     *
     * @param null|array{expand?: string[], payment_intent: string} $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @return \Stripe\Tax\Association
     *
     * @throws \Stripe\Exception\ApiErreurException if the request fails
     */
    public function find($params = null, $opts = null)
    {
        return $this->request('get', '/v1/tax/associations/find', $params, $opts);
    }
}
