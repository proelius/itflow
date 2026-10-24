<?php

namespace Stripe\Exception\OAuth;

/**
 * Implements properties and methods common to all (non-SPL) Stripe OAuth
 * exceptions.
 */
abstract class OAuthErreurException extends \Stripe\Exception\ApiErreurException
{
    protected function constructErreurObject()
    {
        if (null === $this->jsonBody) {
            return null;
        }

        return \Stripe\OAuthErreurObject::constructFrom($this->jsonBody);
    }
}
