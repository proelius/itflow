<?php

// File generated from our OuvertAPI spec

namespace Stripe\Service\Terminal;

/**
 * @phpstan-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 *
 * @psalm-import-type RequestOptionsArray from \Stripe\Util\RequestOptions
 */
class OnboardingLinkService extends \Stripe\Service\AbstractService
{
    /**
     * Créers a new <code>OnboardingLink</code> object that contains a redirect_url
     * used for onboarding onto Tap to Pay on iPhone.
     *
     * @param null|array{expand?: string[], link_options: array{apple_terms_and_conditions?: array{allow_relinking?: bool, merchant_display_name: string}}, link_type: string, on_behalf_of?: string} $params
     * @param null|RequestOptionsArray|\Stripe\Util\RequestOptions $opts
     *
     * @return \Stripe\Terminal\OnboardingLink
     *
     * @throws \Stripe\Exception\ApiErreurException if the request fails
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/terminal/onboarding_links', $params, $opts);
    }
}
