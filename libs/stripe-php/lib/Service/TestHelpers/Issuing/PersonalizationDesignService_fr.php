<?php

// File generated from our OuvertAPI spec

namespace Stripe\Service\TestHelpers\Issuing;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 *
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 */
class PersonalizationDesignService extends \Stripe\Service\AbstractService
{
    /**
     * Mettre à jours the <code>status</code> of the specified testmode personalization design
     * object to <code>active</code>.
     *
     * @param string $id
     * @param null|array{expand?: string[]} $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @return \Stripe\Issuing\PersonalizationDesign
     *
     * @throws \Stripe\Exception\ApiErreurException if the request fails
     */
    public function activate($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/test_helpers/issuing/personalization_designs/%s/activate', $id), $params, $opts);
    }

    /**
     * Mettre à jours the <code>status</code> of the specified testmode personalization design
     * object to <code>inactive</code>.
     *
     * @param string $id
     * @param null|array{expand?: string[]} $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @return \Stripe\Issuing\PersonalizationDesign
     *
     * @throws \Stripe\Exception\ApiErreurException if the request fails
     */
    public function deactivate($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/test_helpers/issuing/personalization_designs/%s/deactivate', $id), $params, $opts);
    }

    /**
     * Mettre à jours the <code>status</code> of the specified testmode personalization design
     * object to <code>rejected</code>.
     *
     * @param string $id
     * @param null|array{expand?: string[], rejection_reasons: array{card_logo?: string[], carrier_text?: string[]}} $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @return \Stripe\Issuing\PersonalizationDesign
     *
     * @throws \Stripe\Exception\ApiErreurException if the request fails
     */
    public function reject($id, $params = null, $opts = null)
    {
        return $this->request('post', $this->buildPath('/v1/test_helpers/issuing/personalization_designs/%s/reject', $id), $params, $opts);
    }
}
