<?php

namespace AdvertisingApi\GoogleApi;

use AdvertisingApi\Config;
use AdvertisingApi\Logger;
use Google\Ads\GoogleAds\Lib\V16\GoogleAdsClientBuilder;
use Google\Ads\GoogleAds\Lib\OAuth2TokenBuilder;
use Google\Ads\GoogleAds\Lib\V16\GoogleAdsClient;

/**
 * Authentication client for Google Ads API
 * 
 * Handles OAuth2 authentication process and token management for Google Ads API,
 * including authentication setup and client creation.
 */
class AuthClient
{
    private GoogleAdsClient $authClient;

    private array $config;
    private Logger $logger;

    public function __construct()
    {
        $this->config = Config::get('google_ads');
        $this->logger = Logger::get();

        $this->initializeAuth();
    }

    /**
     * Initialize OAuth authentication process for Google Ads API
     * 
     * Sets up OAuth2 credentials and builds the Google Ads client with:
     * - OAuth2 credentials (client ID, secret, refresh token)
     * - Developer token for API access
     * - Login customer ID for MCC account
     * 
     * @throws \Exception If authentication setup fails
     */
    private function initializeAuth(): void
    {
        try {
            $oAuth2Credential = (new OAuth2TokenBuilder())
                ->withClientId($this->config['client_id'])
                ->withClientSecret($this->config['client_secret'])
                ->withRefreshToken($this->config['refresh_token'])
                ->build();

            $this->authClient = (new GoogleAdsClientBuilder())
                ->withOAuth2Credential($oAuth2Credential)
                ->withDeveloperToken($this->config['developer_token'])
                ->withLoginCustomerId($this->config['login_customer_id'])
                ->build();
        }
        catch (\Exception $e) {
            $this->logger->get()->error(sprintf("An error occurred: %s", $e->getMessage()), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Returns the authenticated Google Ads client
     * 
     * @return GoogleAdsClient Authenticated client instance
     */
    public function getClient(): GoogleAdsClient
    {
        return $this->authClient;
    }
}
