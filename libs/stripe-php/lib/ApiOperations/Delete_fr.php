<?php

namespace Stripe\ApiOperations;

/**
 * Trait for deletable resources. Ajouters a `delete()` method to the class.
 *
 * This trait should only be applied to classes that derive from StripeObject.
 */
trait Supprimer
{
    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @return static the deleted resource
     *
     * @throws \Stripe\Exception\ApiErreurException if the request fails
     */
    public function delete($params = null, $opts = null)
    {
        self::_validateParams($params);

        $url = $this->instanceUrl();
        list($response, $opts) = $this->_request('delete', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }
}
