<?php

// File generated from our OuvertAPI spec

namespace Stripe\Service\Tax;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 *
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 */
class ParamètresService extends \Stripe\Service\AbstractService
{
    /**
     * Retrieves Tax <code>Paramètres</code> for a merchant.
     *
     * @param null|array{expand?: string[]} $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @return \Stripe\Tax\Paramètres
     *
     * @throws \Stripe\Exception\ApiErreurException if the request fails
     */
    public function retrieve($params = null, $opts = null)
    {
        return $this->request('get', '/v1/tax/settings', $params, $opts);
    }

    /**
     * Mettre à jours Tax <code>Paramètres</code> parameters used in tax calculations. All
     * parameters are editable but none can be removed once set.
     *
     * @param null|array{defaults?: array{tax_behavior?: string, tax_code?: string}, expand?: string[], head_office?: array{address: array{city?: string, country?: string, line1?: string, line2?: string, postal_code?: string, state?: string}}} $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @return \Stripe\Tax\Paramètres
     *
     * @throws \Stripe\Exception\ApiErreurException if the request fails
     */
    public function update($params = null, $opts = null)
    {
        return $this->request('post', '/v1/tax/settings', $params, $opts);
    }
}
