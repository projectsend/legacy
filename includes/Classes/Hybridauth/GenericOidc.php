<?php

namespace ProjectSend\Classes\Hybridauth;

use Hybridauth\Adapter\OAuth2;
use Hybridauth\Exception\InvalidApplicationCredentialsException;
use Hybridauth\Exception\UnexpectedApiResponseException;
use Hybridauth\Data;
use Hybridauth\User;

/**
 * Generic OpenID Connect provider.
 *
 * Works with any standards-compliant OIDC server (Keycloak, Authentik,
 * Authelia, Dex, Okta, etc.) by auto-discovering endpoints from the
 * issuer's /.well-known/openid-configuration document.
 *
 * Required config:
 *   'issuer_url' => 'https://auth.example.com/realms/myrealm'
 *   'keys'       => ['id' => '...', 'secret' => '...']
 */
class GenericOidc extends OAuth2
{
    public $scope = 'openid profile email';

    protected function configure()
    {
        parent::configure();

        if (!$this->config->exists('issuer_url') || !$this->config->get('issuer_url')) {
            throw new InvalidApplicationCredentialsException(
                'You must define an issuer_url for the OIDC provider.'
            );
        }

        $issuer = rtrim($this->config->get('issuer_url'), '/');
        $discovery = $this->fetchDiscovery($issuer);

        $this->apiBaseUrl       = $discovery['userinfo_endpoint'];
        $this->authorizeUrl     = $discovery['authorization_endpoint'];
        $this->accessTokenUrl   = $discovery['token_endpoint'];
    }

    private function fetchDiscovery(string $issuer): array
    {
        $url = $issuer . '/.well-known/openid-configuration';

        $response = $this->httpClient->request($url);
        $data = json_decode($response, true);

        if (empty($data['authorization_endpoint']) || empty($data['token_endpoint']) || empty($data['userinfo_endpoint'])) {
            throw new UnexpectedApiResponseException(
                'OIDC discovery document at ' . $url . ' is missing required fields.'
            );
        }

        return $data;
    }

    public function getUserProfile()
    {
        $response = $this->apiRequest($this->apiBaseUrl);
        $data = new Data\Collection($response);

        if (!$data->exists('sub')) {
            throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
        }

        /**
         * The email address is what maps this identity onto a ProjectSend
         * account, so an identity provider that hands over an address it never
         * checked is enough to take over any account holding that address.
         * Providers that allow self registration, which is the common case for
         * the self hosted servers this adapter exists for, will happily do
         * exactly that. Refuse the login unless the provider states the
         * address is verified.
         */
        $email = $data->get('email');
        if (empty($email)) {
            throw new UnexpectedApiResponseException(
                __('The identity provider did not return an email address, which is required to sign in.', 'cftp_admin')
            );
        }

        $email_verified = (bool) $data->get('email_verified');
        if (!$email_verified && get_option('oidc_require_verified_email', null, '1') == '1') {
            throw new UnexpectedApiResponseException(
                __('The identity provider did not report this email address as verified.', 'cftp_admin') . ' '
                . __('Verify the address with your identity provider, or turn off "Require verified email address" in the social login options if your provider does not send the email_verified claim.', 'cftp_admin')
            );
        }

        $userProfile = new User\Profile();

        $userProfile->identifier   = $data->get('sub');
        $userProfile->email        = $email;
        $userProfile->firstName    = $data->get('given_name');
        $userProfile->lastName     = $data->get('family_name');
        $userProfile->displayName  = $data->get('preferred_username') ?: $data->get('name');
        $userProfile->emailVerified = $email_verified;

        return $userProfile;
    }
}
